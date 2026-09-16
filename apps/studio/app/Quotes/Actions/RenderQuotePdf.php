<?php

namespace App\Quotes\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\QuoteLayout;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\DiscountCalculator;
use App\Quotes\Support\Layouts\ClassicQuoteLayout;
use App\Quotes\Support\Layouts\HeaderBandQuoteLayout;
use App\Quotes\Support\Layouts\LetterQuoteLayout;
use App\Quotes\Support\Layouts\MinimalQuoteLayout;
use App\Quotes\Support\Layouts\QuoteLayoutRenderer;
use App\Quotes\Support\Layouts\StructuredQuoteLayout;
use App\Quotes\Support\QuoteRange;
use App\Quotes\Support\VatLines;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Support\Number;

/**
 * Der Weg, den RenderInvoiceJob schon geht: Vorlage,
 * MergeFieldResolver, DocumentRenderer, Gotenberg. Was hier dazukommt, ist
 * das, was keine Vorlage tragen kann — die Positionen und die Summen des
 * Angebots selbst, zwischen Einleitung und Schluss.
 *
 * ponytail: rendert bei jedem Download neu statt einmal in den Speicher.
 * Ein Angebot ist nach dem Versenden unveränderlich (Quote::booted()), die
 * Bytes sind also bei jedem Lauf dieselben. Eine artifact_path-Spalte lohnt
 * erst, wenn der Gotenberg-Aufruf pro Klick weh tut.
 */
class RenderQuotePdf
{
    public function __construct(
        private readonly GotenbergClient $gotenberg,
        private readonly StudioSettingsResolver $settings,
        private readonly DocumentMergeDataBuilder $mergeData,
        private readonly DocumentRenderer $renderer,
    ) {}

    public function __invoke(Quote $quote): string
    {
        return $this->gotenberg->htmlToPdf($this->html($quote));
    }

    public function html(Quote $quote, ?QuoteLayout $layoutOverride = null): string
    {
        $quote = $quote->asSeenByClient();
        $project = $quote->project;
        $data = $this->mergeData->fromProject($project, $this->settings);

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Quote)
            ->forShootKind($project->primaryShoot?->kind)
            ->orderBy('id')
            ->first();

        $templateBody = MergeFieldResolver::resolve($template?->body ?? '', $data)
            .$this->letter($quote->intro, $data);

        $body = $quote->hasSlider()
            ? $this->recipient($quote).$templateBody.$this->sliderItems($quote)
            : $this->layoutRenderer($layoutOverride)->render($quote, $data, $templateBody);

        $body .= $this->paymentStages($quote);
        $body .= $this->letter($quote->outro, $data);

        return $this->renderer->wrap(DocumentTemplateType::Quote, $body, $this->settings);
    }

    private function layoutRenderer(?QuoteLayout $override = null): QuoteLayoutRenderer
    {
        $layout = $override ?? QuoteLayout::tryFrom($this->settings->quoteLayout()) ?? QuoteLayout::Classic;

        return match ($layout) {
            QuoteLayout::Classic => new ClassicQuoteLayout,
            QuoteLayout::Minimal => new MinimalQuoteLayout,
            QuoteLayout::Letter => new LetterQuoteLayout,
            QuoteLayout::HeaderBand => new HeaderBandQuoteLayout,
            QuoteLayout::Structured => new StructuredQuoteLayout,
        };
    }

    /**
     * Name kommt aus der Kontakt-Relation (das Angebot speichert ihn nicht),
     * Straße/PLZ/Ort aus den eigenen Spalten des Angebots — ein Umzug nach
     * dem Versenden darf das verschickte Dokument nicht mehr ändern.
     */
    private function recipient(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '';
        }

        $lines = [e($name)];

        if ($quote->recipient_street !== null) {
            $lines[] = e($quote->recipient_street);
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));

        if ($cityLine !== '') {
            $lines[] = e($cityLine);
        }

        return '<p>'.implode('<br>', $lines).'</p>';
    }

    /** Freitext, kein HTML: escaped, Absätze bleiben Absätze. */
    private function letter(?string $text, array $data): string
    {
        $resolved = trim(MergeFieldResolver::resolve($text ?? '', $data));

        if ($resolved === '') {
            return '';
        }

        return collect(preg_split('/\n{2,}/', $resolved))
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph))).'</p>')
            ->implode('');
    }

    /**
     * The same split CreatePaymentPlan uses at booking (PaymentStageSplitter),
     * against the quote's own would-be gross total — so a client reading the
     * quote sees the same shares the plan eventually charges, not a second
     * arithmetic invented for the document.
     */
    private function paymentStages(Quote $quote): string
    {
        if ($quote->paymentStages->isEmpty()) {
            return '';
        }

        $rows = $quote->paymentSplit();

        if ($rows === []) {
            return '';
        }

        $body = collect($rows)->map(fn (array $row) => $this->row(e($row['label']), $this->money($row['amount'])))->implode('');

        return '<table class="doc-table"><thead><tr><th colspan="2">Zahlungsplan</th></tr></thead><tbody>'.$body.'</tbody></table>';
    }

    private function sliderItems(Quote $quote): string
    {
        return '<table><tbody>'.$this->sliderRows($quote).'</tbody></table>';
    }

    /**
     * Q-15: a slider quote in print. Accepted, it is the configuration the
     * client chose and a single sum — the same breakdown the portal rail
     * shows, in the order the amount comes about. Not yet accepted, it has
     * no sum at all, so the last row is the range.
     */
    private function sliderRows(Quote $quote): string
    {
        $accepted = $quote->status === QuoteStatus::Accepted;
        $hours = $quote->hours ?? $quote->min_hours ?? 0;
        $hideDiscount = $quote->discount_hidden && $quote->discount_amount > 0;

        // Accepted with a hidden discount: the rows carry it, pro rata.
        $amounts = ['time' => $hours * (int) $quote->hourly_rate];

        foreach ($quote->items->values() as $index => $item) {
            if (! $item->isIncluded() && (! $item->selectable || ($accepted && $item->selected))) {
                $amounts[$index] = $item->discountedLineTotal();
            }
        }

        if ($accepted && $hideDiscount) {
            $amounts = DiscountCalculator::spread($amounts, (int) $quote->net);
        }

        $timeRow = $accepted
            ? $this->row($this->timeLine($quote, $hours), $this->money($amounts['time']))
            : $this->row(
                $quote->time_title === null
                    ? e(sprintf('%d–%d Stunden', $quote->min_hours ?? 0, $quote->max_hours ?? 0))
                    : $this->timeLine($quote, $hours),
                $this->money($quote->hourly_rate).' je Stunde',
            );

        $html = '';
        $position = $quote->time_sort_order ?? 0;

        foreach ($quote->items->values() as $index => $item) {
            if ($index === $position) {
                $html .= $timeRow;
            }

            // Before the acceptance an optional block is an offer, not a
            // line: it prints with its price and no claim that it is in.
            $chosen = ! $item->selectable || ($accepted && $item->selected);

            if ($accepted && ! $chosen) {
                continue;
            }

            $html .= $this->row(
                e($item->title).($item->outcome === null ? '' : '<br><small>'.e($item->outcome).'</small>'),
                $item->isIncluded() ? 'enthalten' : $this->money($amounts[$index] ?? $item->discountedLineTotal()),
            );
        }

        if ($position >= $quote->items->count()) {
            $html .= $timeRow;
        }

        // ponytail: vor der Zusage bleiben Stundensatz und Bausteine beim
        // verborgenen Rabatt Listenpreise, nur die Spanne ist rabattiert.
        $discount = match (true) {
            $quote->discount_hidden || $quote->discount_type === null => '',
            $accepted => $quote->discount_amount > 0
                ? $this->row('Zwischensumme', $this->money($quote->total)).$this->row('Rabatt', '−'.$this->money($quote->discount_amount))
                : '',
            default => $this->row('Rabatt', $quote->discount_type === DiscountType::Percentage
                ? VatLines::percent((int) $quote->discount_value).' %'
                : '−'.$this->money((int) $quote->discount_value)),
        };

        return $html.$discount.($accepted
            ? $this->row('Gesamt', $this->money($quote->net))
            : $this->row('Spanne', $this->money(QuoteRange::min($quote)).' – '.$this->money(QuoteRange::max($quote))));
    }

    /** Ohne Titel die alte Zeile; mit Titel dessen Name, darunter Beschreibung, Stunden und Bildschätzung. */
    private function timeLine(Quote $quote, int $hours): string
    {
        if ($quote->time_title === null) {
            return e($hours.' Stunden');
        }

        $detail = implode(' · ', array_filter([
            $quote->time_description,
            $hours.' Stunden',
            $quote->images_per_hour ? 'ca. '.($hours * $quote->images_per_hour).' bearbeitete Bilder' : null,
        ]));

        return e($quote->time_title).($detail === '' ? '' : '<br><small>'.e($detail).'</small>');
    }

    private function row(string $label, string $value): string
    {
        return '<tr><td>'.$label.'</td><td align="right">'.$value.'</td></tr>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
