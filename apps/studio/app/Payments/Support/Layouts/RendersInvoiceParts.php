<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * The parts every invoice layout prints identically, because they carry no
 * layout of their own: the recipient's lines, the reference line a Gutschrift
 * needs, the § 19 sentence, and the Zahlung block.
 *
 * The positions table and the summary chain are deliberately NOT here — each
 * layout's column count and summary container differ, which is the whole
 * point of having five of them.
 */
trait RendersInvoiceParts
{
    private function recipientLines(InvoiceDocument $document): string
    {
        return collect($document->recipient)->map(fn (string $line) => e($line))->implode('<br>');
    }

    private function reference(InvoiceDocument $document): string
    {
        return $document->reference === null ? '' : '<p>'.e($document->reference).'</p>';
    }

    private function vatNote(InvoiceDocument $document): string
    {
        return $document->vatNote === '' ? '' : '<p>'.e($document->vatNote).'</p>';
    }

    private function payment(InvoiceDocument $document): string
    {
        if ($document->paymentLines === []) {
            return '';
        }

        return '<h3>Zahlung</h3><p>'
            .collect($document->paymentLines)->map(fn (string $line) => e($line))->implode('<br>')
            .'</p>';
    }

    /**
     * One line today — the installment's own description and its gross. #411
     * turns this into the quote's positions and #412 into a row per tax rate;
     * both replace this method's body and nothing around it.
     */
    private function positions(InvoiceDocument $document, int $labelColumns = 1): string
    {
        $filler = str_repeat('<td class="doc-num"></td>', $labelColumns - 1);

        return '<table class="doc-table"><thead><tr><th>Beschreibung</th>'
            .str_repeat('<th class="doc-num"></th>', $labelColumns - 1)
            .'<th class="doc-num">Betrag</th></tr></thead><tbody>'
            .'<tr><td>'.e($document->description).'</td>'.$filler
            .'<td class="doc-num">'.e($document->sign.$document->gross).'</td></tr>'
            .'</tbody></table>';
    }
}
