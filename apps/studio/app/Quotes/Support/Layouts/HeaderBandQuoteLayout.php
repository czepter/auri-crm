<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\VatLines;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Kopfband" story. Farbfläche oben in Akzentfarbe,
 * Meta-Zeile darunter, Tabellenkopf akzentfarben, Summenblock als Karte.
 */
class HeaderBandQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];

        return '<div class="doc-layout-header_band">'
            .'<div class="dhb-band"><span>'.e($studio['name'] ?? '').'</span><span class="dhb-band-title">Angebot'.($quote->number === null ? '' : '<br><small>Nr. '.e($quote->number).'</small>').'</span></div>'
            .'<div class="dhb-body">'
            .'<div class="dhb-meta-row">'
            .'<span>Datum <strong>'.(($quote->sent_at ?? $quote->created_at)?->format('d.m.Y') ?? '').'</strong></span>'
            .($quote->valid_until === null ? '' : '<span>Gültig bis <strong>'.e($quote->valid_until->format('d.m.Y')).'</strong></span>')
            .'</div>'
            .$this->recipient($quote)
            .$templateBody
            .$this->items($quote)
            .'</div></div>';
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

        return '<div class="dhb-to">'.implode('<br>', $lines).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->label()).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        $summary = '<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $summary .= '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $summary .= '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            // #412: eine Zeile je Steuersatz — bei einem Satz ist das
            // genau die eine Zeile, die hier immer stand.
            foreach ($quote->vatLines() as $line) {
                $summary .= '<tr><td>USt. '.VatLines::percent($line['rate']).' %</td><td class="doc-num">'.$this->money($line['vat']).'</td></tr>';
            }
            $summary .= '<tr class="dhb-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<table class="dhb-summary">'.$summary.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
