<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\VatLines;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Klassisch" story. Absender/Empfänger links, eine
 * Meta-Box rechts (Angebotsnr., Datum, Gültig bis), Positionstabelle mit
 * Kopfzeile, Summenblock rechtsbündig darunter.
 */
class ClassicQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])
            ->filter()
            ->implode(' · ');

        return '<div class="doc-layout-classic">'
            .'<div class="dlc-head">'
            .'<div>'
            .($studioLine === '' ? '' : '<div class="dlc-from">'.e($studioLine).'</div>')
            .$this->recipient($quote)
            .'</div>'
            .$this->meta($quote)
            .'</div>'
            .$templateBody
            .$this->items($quote)
            .'</div>';
    }

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

        return '<div class="dlc-recipient">'.implode('<br>', $lines).'</div>';
    }

    private function meta(Quote $quote): string
    {
        $rows = [
            ['Angebotsnr.', $quote->number ?? ''],
            ['Datum', $quote->created_at?->format('d.m.Y') ?? ''],
        ];

        if ($quote->valid_until !== null) {
            $rows[] = ['Gültig bis', $quote->valid_until->format('d.m.Y')];
        }

        $cells = collect($rows)
            ->map(fn (array $row) => '<tr><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<table class="dlc-meta"><tr><td colspan="2" class="dlc-title">Angebot</td></tr>'.$cells.'</table>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => $this->row(
            e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>'),
            (string) $item->quantity,
            $item->isIncluded() ? '' : $this->money($item->unit_price),
            $item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()),
        ))->implode('');

        return '<table class="doc-table"><thead><tr>'
            .'<th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Einzelpreis</th><th class="doc-num">Betrag</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .$this->summary($quote);
    }

    private function row(string $label, string $quantity, string $unitPrice, string $amount): string
    {
        return '<tr><td>'.$label.'</td><td class="doc-num">'.$quantity.'</td><td class="doc-num">'.$unitPrice.'</td><td class="doc-num">'.$amount.'</td></tr>';
    }

    private function summary(Quote $quote): string
    {
        $rows = ['<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>'];

        if ($quote->discount_amount > 0) {
            $rows[] = '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows[] = '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            // #412: eine Zeile je Steuersatz — bei einem Satz ist das
            // genau die eine Zeile, die hier immer stand.
            foreach ($quote->vatLines() as $line) {
                $rows[] = '<tr><td>USt. '.VatLines::percent($line['rate']).' %</td><td class="doc-num">'.$this->money($line['vat']).'</td></tr>';
            }
            $rows[] = '<tr class="dlc-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="dlc-summary">'.implode('', $rows).'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
