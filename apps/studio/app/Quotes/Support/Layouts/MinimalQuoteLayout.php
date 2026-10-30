<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\VatLines;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Minimal" story. Keine Meta-Box, keine
 * Tabellenlinien — Menge inline im Titel statt in einer eigenen Spalte,
 * dieselbe Anordnung wie resources/js/portal/pages/Quotes/Show.vue.
 */
class MinimalQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        return '<div class="doc-layout-minimal">'
            .'<div class="dlm-head">'
            .'<span>Angebot'.($quote->number === null ? '' : ' '.e($quote->number)).'</span>'
            .($quote->valid_until === null ? '' : '<span>Gültig bis '.e($quote->valid_until->format('d.m.Y')).'</span>')
            .'</div>'
            .$this->recipient($quote)
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

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));
        $address = collect([$quote->recipient_street, $cityLine])->filter()->implode(', ');

        return '<div class="dlm-to">für <strong>'.e($name).'</strong>'.($address === '' ? '' : ' · '.e($address)).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(function (QuoteItem $item) {
            $title = e($item->label()).($item->quantity > 1 ? ' · '.$item->quantity.' × '.$this->money($item->unit_price ?? 0) : '');
            $desc = $item->description === null ? '' : '<div class="dlm-desc">'.e($item->description).'</div>';
            $amount = $item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal());

            return '<tr><td>'.$title.$desc.'</td><td>'.$amount.'</td></tr>';
        })->implode('');

        $rows .= '<tr class="dlm-total"><td>Zwischensumme</td><td>'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $rows .= '<tr><td>Rabatt</td><td>−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows .= '<tr><td>Netto</td><td>'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            // #412: eine Zeile je Steuersatz — bei einem Satz ist das
            // genau die eine Zeile, die hier immer stand.
            foreach ($quote->vatLines() as $line) {
                $rows .= '<tr><td>USt. '.VatLines::percent($line['rate']).' %</td><td>'.$this->money($line['vat']).'</td></tr>';
            }
            $rows .= '<tr class="dlm-grand"><td>Gesamtbetrag</td><td>'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="dlm-table">'.$rows.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
