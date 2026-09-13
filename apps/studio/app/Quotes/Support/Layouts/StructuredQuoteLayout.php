<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Struktur" story. Empfänger- und Meta-Karte
 * nebeneinander, Tabelle mit alternierenden Zeilen in einer Karte,
 * Summenblock und Fußzeile ebenfalls als Karten.
 */
class StructuredQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        return '<div class="doc-layout-structured">'
            .'<div class="dst-head"><span>Angebot'.($quote->number === null ? '' : ' Nr. '.e($quote->number)).'</span></div>'
            .'<div class="dst-cards">'.$this->recipientCard($quote).$this->metaCard($quote).'</div>'
            .$templateBody
            .'<div class="dst-card dst-items">'.$this->items($quote).'</div>'
            .'<div class="dst-card dst-summary">'.$this->summary($quote).'</div>'
            .'</div>';
    }

    private function recipientCard(Quote $quote): string
    {
        $name = $quote->recipientContact?->name;

        if ($name === null) {
            return '<div class="dst-card"></div>';
        }

        $cityLine = trim(($quote->recipient_postcode ?? '').' '.($quote->recipient_city ?? ''));
        $lines = collect([$quote->recipient_street, $cityLine])->filter()->map(fn (string $l) => e($l))->implode('<br>');

        return '<div class="dst-card"><div class="dst-label">Empfänger</div><div><strong>'.e($name).'</strong><br>'.$lines.'</div></div>';
    }

    private function metaCard(Quote $quote): string
    {
        $rows = ['<span>Datum</span><span>'.($quote->created_at?->format('d.m.Y') ?? '').'</span>'];

        if ($quote->valid_until !== null) {
            $rows[] = '<span>Gültig bis</span><span>'.e($quote->valid_until->format('d.m.Y')).'</span>';
        }

        return '<div class="dst-card"><div class="dst-label">Details</div><div class="dst-meta-grid">'.implode('', $rows).'</div></div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function summary(Quote $quote): string
    {
        $rows = '<tr><td>Zwischensumme</td><td class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $rows .= '<tr><td>Rabatt</td><td class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $rows .= '<tr><td>Netto</td><td class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $rows .= '<tr><td>USt. '.$rate.' %</td><td class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $rows .= '<tr class="dst-total"><td>Gesamtbetrag</td><td class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table>'.$rows.'</table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
