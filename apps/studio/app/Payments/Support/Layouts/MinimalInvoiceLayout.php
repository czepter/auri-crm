<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * Das Gegenstück zu App\Quotes\Support\Layouts\MinimalQuoteLayout: keine
 * Meta-Box, keine Tabellenlinien, Kopfdaten als Zeile über dem Empfänger.
 */
class MinimalInvoiceLayout implements InvoiceLayoutRenderer
{
    use RendersInvoiceParts;

    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string
    {
        $number = collect($document->metaRows)->firstWhere(0, 'Rechnungsnummer')
            ?? collect($document->metaRows)->firstWhere(0, 'Gutschriftnummer');

        $head = '<span>'.e($document->title.($number === null ? '' : ' '.$number[1])).'</span>'
            .collect($document->metaRows)
            ->reject(fn (array $row) => str_ends_with($row[0], 'nummer'))
            ->map(fn (array $row) => '<span>'.e($row[0].' '.$row[1]).'</span>')
            ->implode('');

        $rows = $this->positionRows($document);

        // Wie im Angebot: die Linie sitzt auf der ersten Summenzeile, fett
        // wird nur der Gesamtbetrag.
        foreach ($document->summaryRows() as $index => $row) {
            $classes = array_filter([$index === 0 ? 'dlm-total' : null, $row[2] ? 'dlm-grand' : null]);
            $class = $classes === [] ? '' : ' class="'.implode(' ', $classes).'"';

            $rows .= '<tr'.$class.'><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td></tr>';
        }

        $name = $document->recipient[0] ?? '';
        $address = collect($document->recipient)->skip(1)->implode(', ');

        return '<div class="doc-layout-minimal">'
            .'<div class="dlm-head">'.$head.'</div>'
            .'<div class="dlm-to">für <strong>'.e($name).'</strong>'.($address === '' ? '' : ' · '.e($address)).'</div>'
            .$this->reference($document)
            .$templateBody
            .$this->letter($document->intro)
            .'<table class="dlm-table">'.$rows.'</table>'
            .$this->vatNote($document)
            .$this->letter($document->outro)
            .$this->payment($document)
            .'</div>';
    }
}
