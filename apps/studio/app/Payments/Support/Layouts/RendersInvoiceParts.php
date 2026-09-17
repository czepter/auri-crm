<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;
use Illuminate\Support\Number;

/**
 * The parts every invoice layout prints identically, because they carry no
 * layout of their own: the recipient's lines, the reference line a Gutschrift
 * needs, the § 19 sentence, and the Zahlung block.
 *
 * The summary chain is deliberately NOT here — each layout's summary
 * container differs, which is the whole point of having five of them. The
 * positions table is, since #411 gave it rows no layout should assemble
 * twice; how wide it is stays the layout's own answer.
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

    /** #480: sanitised at save, merge fields escaped by InvoiceDocument. */
    private function letter(string $html): string
    {
        return $html;
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
     * The positions table. An Anzahlungsrechnung and a Gutschrift have one
     * row — the document's own description and its gross. An Endrechnung
     * (#411) has the quote's lines, the whole Leistung, then every earlier
     * Teilentgelt as a deduction and the remainder.
     *
     * $columns is how wide the layout's table is: four where it carries Menge
     * and Einzelpreis of its own, two where the quantity belongs in the
     * description cell.
     */
    private function positions(InvoiceDocument $document, int $columns = 4): string
    {
        $head = $columns >= 4
            ? '<th>Beschreibung</th><th class="doc-num">Menge</th><th class="doc-num">Einzelpreis</th><th class="doc-num">Betrag</th>'
            : '<th>Beschreibung</th><th class="doc-num">Betrag</th>';

        return '<table class="doc-table"><thead><tr>'.$head.'</tr></thead><tbody>'
            .$this->positionRows($document, $columns)
            .'</tbody></table>';
    }

    /**
     * The rows on their own, for the two layouts that print them inside a
     * table of their own making (Minimal and Letter fold the summary chain
     * into the same table, which is their whole point).
     */
    private function positionRows(InvoiceDocument $document, int $columns = 2): string
    {
        if ($document->lines === []) {
            return $this->positionRow($columns, e($document->description), '', '', $document->sign.$document->gross);
        }

        $rows = '';
        // The rate per line is only worth printing where the document breaks
        // out more than one; otherwise the summary chain already names it.
        $showRate = count($document->vatLines) > 1;

        foreach ($document->lines as $line) {
            $rows .= $this->positionRow(
                $columns,
                $this->lineLabel($line, $columns, $showRate),
                $line['quantity'],
                $line['unitPrice'],
                $line['total'],
            );
        }

        $rows .= $this->spanningRow($columns, 'Gesamtbetrag der Leistung', $document->totalGross);

        if ($document->deposits === []) {
            return $rows;
        }

        $rows .= $this->spanningRow($columns, 'Abzüglich Anzahlungen', '');

        foreach ($document->deposits as $deposit) {
            $rows .= $this->spanningRow(
                $columns,
                "Rechnung {$deposit['number']} vom {$deposit['date']}",
                '−'.$deposit['gross'],
                $document->smallBusiness ? '' : "netto {$deposit['net']} · USt {$deposit['vat']}",
            );
        }

        // § 14 Abs. 5 S. 2 UStG: the tax already invoiced is deducted by amount.
        if (! $document->smallBusiness) {
            $vat = array_sum(array_column($document->deposits, 'vatAmount'));
            $rows .= $this->spanningRow($columns, 'davon USt', '−'.Number::currency($vat / 100, in: 'EUR', locale: 'de'));
        }

        return $rows.$this->spanningRow($columns, 'Restbetrag', $document->gross);
    }

    /**
     * Quantity and unit price move into the label where the table has no
     * column for them; the line discount and — only where the document
     * carries more than one — the rate sit under the title either way.
     *
     * @param  array<string, string>  $line
     */
    private function lineLabel(array $line, int $columns, bool $showRate): string
    {
        $detail = array_filter([
            $columns >= 4 || $line['unitPrice'] === '' ? null : $line['quantity'].' × '.$line['unitPrice'],
            $line['discount'] === '' ? null : 'Rabatt '.$line['discount'],
            $showRate && $line['vatRate'] !== '' ? $line['vatRate'] : null,
        ]);

        return e($line['title'])
            .($detail === [] ? '' : '<br><small>'.e(implode(' · ', $detail)).'</small>');
    }

    private function positionRow(int $columns, string $label, string $quantity, string $unitPrice, string $amount): string
    {
        $middle = $columns >= 4
            ? '<td class="doc-num">'.e($quantity).'</td><td class="doc-num">'.e($unitPrice).'</td>'
            : '';

        return '<tr><td>'.$label.'</td>'.$middle.'<td class="doc-num">'.e($amount).'</td></tr>';
    }

    private function spanningRow(int $columns, string $label, string $amount, string $detail = ''): string
    {
        $span = $columns >= 4 ? ' colspan="3"' : '';
        $detail = $detail === '' ? '' : '<br><small>'.e($detail).'</small>';

        return '<tr><td'.$span.'>'.e($label).$detail.'</td><td class="doc-num">'.e($amount).'</td></tr>';
    }
}
