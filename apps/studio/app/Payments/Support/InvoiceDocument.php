<?php

namespace App\Payments\Support;

use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\MergeFieldResolver;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;

/**
 * #419. What the five invoice layouts print, normalised — so a Rechnung and
 * a Gutschrift are one shape and no layout carries the branch between them.
 *
 * Everything here is already a formatted string, taken from the merge data
 * App\Documents\Support\DocumentMergeData built: the euro formatting
 * and the net/VAT split happened there, once, and a layout that recomputed
 * either would be a second opinion about the same figures.
 */
final class InvoiceDocument
{
    /**
     * § 286 Abs. 3 S. 1 BGB: a consumer falls into Verzug 30 days after a due
     * invoice reaches them only if the invoice said so. #422 put it in both
     * starters; here it is neither editable nor duplicated, and a Gutschrift —
     * which has no payment lines at all — never carries it.
     */
    public const DEFAULT_NOTICE = 'Nach 30 Tagen ab Zugang und Fälligkeit tritt Verzug ein (§ 286 Abs. 3 BGB).';

    /**
     * @param  list<array{0: string, 1: string}>  $metaRows
     * @param  list<string>  $recipient
     * @param  list<string>  $paymentLines
     */
    private function __construct(
        public readonly string $title,
        public readonly array $metaRows,
        public readonly string $description,
        public readonly string $net,
        public readonly string $vatRate,
        public readonly string $vat,
        public readonly string $gross,
        public readonly string $vatNote,
        public readonly bool $smallBusiness,
        /** '−' on a Gutschrift, empty on an invoice. */
        public readonly string $sign,
        public readonly array $recipient,
        public readonly array $paymentLines,
        public readonly ?string $reference,
        /**
         * #412: one entry per tax rate, already formatted. A single-rate
         * document has one and prints the ordinary Umsatzsteuer row from it.
         *
         * @var list<array{rate: string, net: string, vat: string}>
         */
        public readonly array $vatLines = [],
        /**
         * #411: the whole Leistung, line by line, on an Endrechnung — empty
         * on every Anzahlungsrechnung and on a Gutschrift, which is what
         * makes the block absent rather than blank.
         *
         * @var list<array<string, string>>
         */
        public readonly array $lines = [],
        /**
         * The Teilentgelte to deduct (§ 14 Abs. 5 S. 2 UStG), each with the
         * number and date of the invoice that took it.
         *
         * @var list<array<string, string>>
         */
        public readonly array $deposits = [],
        public readonly string $totalNet = '',
        public readonly string $totalVat = '',
        public readonly string $totalGross = '',
        /** #480: the invoice's own letter text — sanitised HTML, merge fields resolved. */
        public readonly string $intro = '',
        public readonly string $outro = '',
    ) {}

    /** @param array<string, mixed> $mergeData */
    public static function forInvoice(Invoice $record, array $mergeData): self
    {
        $invoice = $mergeData['invoice'] ?? [];
        $studio = $mergeData['studio'] ?? [];

        $payment = array_values(array_filter([
            $invoice['paymentTerm'] ?? '',
            self::DEFAULT_NOTICE,
            $studio['bankHolder'] ?? '',
            ($studio['bankIban'] ?? '') === '' ? '' : 'IBAN '.$studio['bankIban'],
            ($studio['bankBic'] ?? '') === '' ? '' : 'BIC '.$studio['bankBic'],
            ($invoice['number'] ?? '') === '' ? '' : 'Verwendungszweck '.$invoice['number'],
        ], fn (string $line) => $line !== ''));

        return new self(
            title: 'Rechnung',
            metaRows: array_values(array_filter([
                ['Rechnungsnummer', $invoice['number'] ?? ''],
                ['Rechnungsdatum', $invoice['date'] ?? ''],
                ['Leistungszeitraum', $invoice['servicePeriod'] ?? ''],
                ['Fällig am', $invoice['dueOn'] ?? ''],
                ...self::taxRows($mergeData),
            ], fn (array $row) => $row[1] !== '')),
            description: $invoice['description'] ?? '',
            net: $invoice['net'] ?? '',
            vatRate: $invoice['vatRate'] ?? '',
            vat: $invoice['vat'] ?? '',
            gross: $invoice['gross'] ?? '',
            vatNote: $invoice['vatNote'] ?? '',
            smallBusiness: InvoiceFigures::forInvoice($record)->rate === 0,
            sign: '',
            recipient: self::recipient($mergeData),
            paymentLines: $payment,
            reference: null,
            vatLines: $invoice['vatLines'] ?? [],
            lines: $invoice['lines'] ?? [],
            deposits: $invoice['deposits'] ?? [],
            totalNet: $invoice['totalNet'] ?? '',
            totalVat: $invoice['totalVat'] ?? '',
            totalGross: $invoice['totalGross'] ?? '',
            intro: MergeFieldResolver::resolve(InlineHtmlSanitizer::letter($record->intro) ?? '', $mergeData, escape: true),
            outro: MergeFieldResolver::resolve(InlineHtmlSanitizer::letter($record->outro) ?? '', $mergeData, escape: true),
        );
    }

    /** @param array<string, mixed> $mergeData */
    public static function forCreditNote(CreditNote $creditNote, array $mergeData): self
    {
        $note = $mergeData['creditNote'] ?? [];

        $invoiceNumber = $note['invoiceNumber'] ?? '';
        $invoiceDate = $note['invoiceDate'] ?? '';

        return new self(
            title: 'Gutschrift',
            metaRows: array_values(array_filter([
                ['Gutschriftnummer', $note['number'] ?? ''],
                ['Gutschriftdatum', $note['date'] ?? ''],
                ['Leistungszeitraum', $note['servicePeriod'] ?? ''],
                ...self::taxRows($mergeData),
            ], fn (array $row) => $row[1] !== '')),
            description: $note['description'] ?? '',
            net: $note['net'] ?? '',
            vatRate: $note['vatRate'] ?? '',
            vat: $note['vat'] ?? '',
            gross: $note['gross'] ?? '',
            vatNote: $note['vatNote'] ?? '',
            smallBusiness: InvoiceFigures::forInvoice($creditNote->invoice)->rate === 0,
            sign: '−',
            recipient: self::recipient($mergeData),
            // § 31 Abs. 5 UStDV: the document has to point at the invoice it
            // corrects, specifically and unambiguously.
            paymentLines: [],
            reference: $invoiceNumber === ''
                ? null
                : "Zu Rechnung {$invoiceNumber}".($invoiceDate === '' ? '' : " vom {$invoiceDate}"),
        );
    }

    /**
     * #419/#480: what the portal page and the create screen's portal preview
     * render — the same figures the PDF layouts print.
     *
     * @return array<string, mixed>
     */
    public function page(): array
    {
        return [
            'metaRows' => $this->metaRows,
            'description' => $this->description,
            'lines' => $this->lines,
            'deposits' => $this->deposits,
            'totalGross' => $this->totalGross,
            'summaryRows' => $this->summaryRows(),
            'vatNote' => $this->vatNote,
            'paymentLines' => $this->paymentLines,
            'intro' => $this->intro,
            'outro' => $this->outro,
        ];
    }

    /**
     * Netto → Umsatzsteuer → Gesamtbetrag, or the single Gesamtbetrag a
     * Kleinunternehmer prints instead — § 19 UStG forbids showing a VAT line
     * at all, and `vatNote` carries the sentence that replaces it.
     *
     * #412: one Umsatzsteuer row per rate, which for a quote at a single rate
     * is the one row this always printed.
     *
     * @return list<array{0: string, 1: string, 2: bool}> label, amount, is-total
     */
    public function summaryRows(): array
    {
        if ($this->smallBusiness) {
            return [['Gesamtbetrag', $this->sign.$this->gross, true]];
        }

        $vat = $this->vatLines === []
            ? [['Umsatzsteuer '.$this->vatRate, $this->sign.$this->vat, false]]
            : array_map(
                fn (array $line) => ['Umsatzsteuer '.$line['rate'], $this->sign.$line['vat'], false],
                $this->vatLines,
            );

        return [
            ['Netto', $this->sign.$this->net, false],
            ...$vat,
            ['Gesamtbetrag', $this->sign.$this->gross, true],
        ];
    }

    /**
     * § 14 Abs. 4 Nr. 2 UStG: Steuernummer or USt-IdNr. on every invoice. The
     * layout prints them, so no edit to the letter text can drop them.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function taxRows(array $mergeData): array
    {
        return [
            ['Steuernummer', $mergeData['studio']['taxNumber'] ?? ''],
            ['USt-IdNr.', $mergeData['studio']['vatId'] ?? ''],
        ];
    }

    /** @return list<string> */
    private static function recipient(array $mergeData): array
    {
        $contact = $mergeData['contact'] ?? [];
        $cityLine = trim(($contact['postcode'] ?? '').' '.($contact['city'] ?? ''));

        return array_values(array_filter([
            $contact['name'] ?? '',
            $contact['street'] ?? '',
            $cityLine,
        ], fn (string $line) => $line !== ''));
    }
}
