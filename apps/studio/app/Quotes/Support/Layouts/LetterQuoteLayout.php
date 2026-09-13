<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Number;

/**
 * Reference: apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue, "Brief" story. Ort/Datum oben rechts,
 * Serifen-Überschrift, Anrede vor der Tabelle, Schlussabsatz danach.
 */
class LetterQuoteLayout implements QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])->filter()->implode(' · ');

        return '<div class="doc-layout-letter">'
            .'<div class="dll-top">'
            .'<span>'.e($studioLine).'</span>'
            .'<span>'.($quote->created_at?->format('d.m.Y') ?? '').'</span>'
            .'</div>'
            .'<div class="dll-title">Angebot</div>'
            .'<div class="dll-sub">'.($quote->number === null ? '' : 'Nr. '.e($quote->number)).($quote->valid_until === null ? '' : ' · gültig bis '.e($quote->valid_until->format('d.m.Y'))).'</div>'
            .$this->recipient($quote)
            .$templateBody
            .$this->items($quote)
            .($quote->valid_until === null ? '' : '<p>Das Angebot ist bis zum '.e($quote->valid_until->format('d.m.Y')).' gültig.</p>')
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

        return '<div class="dll-to">An<br>'.implode('<br>', $lines).'</div>';
    }

    private function items(Quote $quote): string
    {
        $rows = $quote->items->map(fn (QuoteItem $item) => '<tr><td>'
            .e($item->title).($item->description === null ? '' : '<br><small>'.e($item->description).'</small>')
            .'</td><td class="doc-num">'.$item->quantity.'</td><td class="doc-num">'
            .($item->isIncluded() ? 'inklusive' : $this->money($item->discountedLineTotal()))
            .'</td></tr>')->implode('');

        $summary = '<tr><td>Zwischensumme</td><td colspan="2" class="doc-num">'.$this->money($quote->total).'</td></tr>';

        if ($quote->discount_amount > 0) {
            $summary .= '<tr><td>Rabatt</td><td colspan="2" class="doc-num">−'.$this->money($quote->discount_amount).'</td></tr>';
            $summary .= '<tr><td>Netto</td><td colspan="2" class="doc-num">'.$this->money($quote->net).'</td></tr>';
        }

        if ($quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',');
            $summary .= '<tr><td>USt. '.$rate.' %</td><td colspan="2" class="doc-num">'.$this->money($quote->vat_amount ?? 0).'</td></tr>';
            $summary .= '<tr class="dll-total"><td>Gesamtbetrag</td><td colspan="2" class="doc-num">'.$this->money($quote->net + ($quote->vat_amount ?? 0)).'</td></tr>';
        }

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Betrag</th></tr></thead>'
            .'<tbody>'.$rows.$summary.'</tbody></table>';
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
