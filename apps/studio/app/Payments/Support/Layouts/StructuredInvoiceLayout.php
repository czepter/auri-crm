<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * Das Gegenstück zu App\Quotes\Support\Layouts\StructuredQuoteLayout:
 * Empfänger- und Meta-Karte nebeneinander, Tabelle und Summenblock je in
 * einer Karte.
 */
class StructuredInvoiceLayout implements InvoiceLayoutRenderer
{
    use RendersInvoiceParts;

    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string
    {
        $number = collect($document->metaRows)->first(fn (array $row) => str_ends_with($row[0], 'nummer'));

        $meta = collect($document->metaRows)
            ->map(fn (array $row) => '<span>'.e($row[0]).'</span><span>'.e($row[1]).'</span>')
            ->implode('');

        $summary = collect($document->summaryRows())
            ->map(fn (array $row) => '<tr'.($row[2] ? ' class="dst-total"' : '').'><td>'.e($row[0])
                .'</td><td class="doc-num">'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<div class="doc-layout-structured">'
            .'<div class="dst-head"><span>'.e($document->title.($number === null ? '' : ' Nr. '.$number[1])).'</span></div>'
            .'<div class="dst-cards">'
            .'<div class="dst-card"><div class="dst-label">Empfänger</div><div>'.$this->recipientLines($document).'</div></div>'
            .'<div class="dst-card"><div class="dst-label">Details</div><div class="dst-meta-grid">'.$meta.'</div></div>'
            .'</div>'
            .$this->reference($document)
            .$templateBody
            .$this->letter($document->intro)
            .'<div class="dst-card dst-items">'.$this->positions($document).'</div>'
            .'<div class="dst-card dst-summary"><table>'.$summary.'</table></div>'
            .$this->vatNote($document)
            .$this->letter($document->outro)
            .$this->payment($document)
            .'</div>';
    }
}
