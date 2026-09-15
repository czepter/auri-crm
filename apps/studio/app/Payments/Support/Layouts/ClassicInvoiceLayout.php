<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * Das Gegenstück zu App\Quotes\Support\Layouts\ClassicQuoteLayout: Absender
 * und Empfänger links, Meta-Box rechts, Positionstabelle mit Kopfzeile,
 * Summenblock rechtsbündig darunter.
 */
class ClassicInvoiceLayout implements InvoiceLayoutRenderer
{
    use RendersInvoiceParts;

    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])->filter()->implode(' · ');

        $meta = collect($document->metaRows)
            ->map(fn (array $row) => '<tr><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td></tr>')
            ->implode('');

        $summary = collect($document->summaryRows())
            ->map(fn (array $row) => '<tr'.($row[2] ? ' class="dlc-total"' : '').'><td>'.e($row[0])
                .'</td><td class="doc-num">'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<div class="doc-layout-classic">'
            .'<div class="dlc-head"><div>'
            .($studioLine === '' ? '' : '<div class="dlc-from">'.e($studioLine).'</div>')
            .'<div class="dlc-recipient">'.$this->recipientLines($document).'</div>'
            .'</div>'
            .'<table class="dlc-meta"><tr><td colspan="2" class="dlc-title">'.e($document->title).'</td></tr>'.$meta.'</table>'
            .'</div>'
            .$this->reference($document)
            .$templateBody
            .$this->positions($document)
            .'<table class="dlc-summary">'.$summary.'</table>'
            .$this->vatNote($document)
            .$this->payment($document)
            .'</div>';
    }
}
