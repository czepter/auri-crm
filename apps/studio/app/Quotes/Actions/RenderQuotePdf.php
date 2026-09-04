<?php

namespace App\Quotes\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Support\Number;

/**
 * Der Weg, den RenderContractJob und RenderInvoiceJob schon gehen: Vorlage,
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
        $project = $quote->project;
        $data = $this->mergeData->fromProject($project, $this->settings);

        $template = DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Quote)
            ->forShootKind($project->primaryShoot?->kind?->value)
            ->orderBy('id')
            ->first();

        $body = MergeFieldResolver::resolve($template?->body ?? '', $data)
            .$this->letter($quote->intro, $data)
            .$this->items($quote)
            .$this->letter($quote->outro, $data);

        return $this->gotenberg->htmlToPdf(
            $this->renderer->wrap(DocumentTemplateType::Quote, $body, $this->settings)
        );
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

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => $this->row(
            e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>'),
            $item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()),
        ))->implode('');

        return '<table><tbody>'.$rows.$this->summary($quote).'</tbody></table>';
    }

    /** Dieselben Zeilen, die der Schirm zeigt — Rabatt und USt. nur, wo es sie gibt. */
    private function summary(Quote $quote): string
    {
        $html = $this->row('Gesamt', $this->money($quote->total));

        if ($quote->discount_amount > 0) {
            $html .= $this->row('Rabatt', '−'.$this->money($quote->discount_amount))
                .$this->row('Netto', $this->money($quote->net));
        }

        if ($quote->vat_mode === 'standard') {
            $html .= $this->row('USt. '.rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',').' %', $this->money($quote->vat_amount ?? 0))
                .$this->row('Gesamtbetrag', $this->money($quote->net + ($quote->vat_amount ?? 0)));
        }

        return $html;
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
