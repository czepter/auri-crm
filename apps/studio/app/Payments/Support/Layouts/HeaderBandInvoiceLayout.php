<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * Das Gegenstück zu App\Quotes\Support\Layouts\HeaderBandQuoteLayout:
 * Farbband oben mit Studioname und Dokumenttitel, Kopfdaten als Zeile
 * darunter, Summenblock als Karte rechts.
 */
class HeaderBandInvoiceLayout implements InvoiceLayoutRenderer
{
    use RendersInvoiceParts;

    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];

        $number = collect($document->metaRows)->first(fn (array $row) => str_ends_with($row[0], 'nummer'));

        $metaRow = collect($document->metaRows)
            ->reject(fn (array $row) => str_ends_with($row[0], 'nummer'))
            ->map(fn (array $row) => '<span>'.e($row[0]).' <strong>'.e($row[1]).'</strong></span>')
            ->implode('');

        $summary = collect($document->summaryRows())
            ->map(fn (array $row) => '<tr'.($row[2] ? ' class="dhb-total"' : '').'><td>'.e($row[0])
                .'</td><td class="doc-num">'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<div class="doc-layout-header_band">'
            .'<div class="dhb-band"><span>'.e($studio['name'] ?? '').'</span>'
            .'<span class="dhb-band-title">'.e($document->title)
            .($number === null ? '' : '<br><small>Nr. '.e($number[1]).'</small>').'</span></div>'
            .'<div class="dhb-body">'
            .'<div class="dhb-meta-row">'.$metaRow.'</div>'
            .'<div class="dhb-to">'.$this->recipientLines($document).'</div>'
            .$this->reference($document)
            .$templateBody
            .$this->positions($document)
            .'<table class="dhb-summary">'.$summary.'</table>'
            .$this->vatNote($document)
            .$this->payment($document)
            .'</div></div>';
    }
}
