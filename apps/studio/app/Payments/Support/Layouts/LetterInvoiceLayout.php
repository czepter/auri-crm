<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * Das Gegenstück zu App\Quotes\Support\Layouts\LetterQuoteLayout: Absender
 * und Datum oben, große Überschrift, Empfänger als Anschrift, Summenkette im
 * Tabellenfuß statt in einem eigenen Block.
 */
class LetterInvoiceLayout implements InvoiceLayoutRenderer
{
    use RendersInvoiceParts;

    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string
    {
        $studio = $mergeData['studio'] ?? [];
        $studioLine = collect([$studio['name'] ?? null, $studio['street'] ?? null])->filter()->implode(' · ');

        $sub = collect($document->metaRows)
            ->map(fn (array $row) => $row[0].' '.$row[1])
            ->implode(' · ');

        $summary = collect($document->summaryRows())
            ->map(fn (array $row) => '<tr'.($row[2] ? ' class="dll-total"' : '').'><td>'.e($row[0])
                .'</td><td class="doc-num">'.e($row[1]).'</td></tr>')
            ->implode('');

        return '<div class="doc-layout-letter">'
            .'<div class="dll-top"><span>'.e($studioLine).'</span><span>'.e($this->issuedOn($document)).'</span></div>'
            .'<div class="dll-title">'.e($document->title).'</div>'
            .'<div class="dll-sub">'.e($sub).'</div>'
            .'<div class="dll-to">An<br>'.$this->recipientLines($document).'</div>'
            .$this->reference($document)
            .$templateBody
            .$this->letter($document->intro)
            .'<table class="doc-table"><thead><tr><th>Beschreibung</th><th class="doc-num">Betrag</th></tr></thead><tbody>'
            .$this->positionRows($document)
            .$summary
            .'</tbody></table>'
            .$this->vatNote($document)
            .$this->letter($document->outro)
            .$this->payment($document)
            .'</div>';
    }

    private function issuedOn(InvoiceDocument $document): string
    {
        $row = collect($document->metaRows)->first(fn (array $candidate) => str_ends_with($candidate[0], 'datum'));

        return $row === null ? '' : $row[1];
    }
}
