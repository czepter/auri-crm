<?php

namespace App\Payments\Support;

use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;

/**
 * #419. What the five invoice layouts print, normalised — so a Rechnung and
 * a Gutschrift are one shape and no layout carries the branch between them.
 *
 * Everything here is already a formatted string, taken from the merge data
 * App\Documents\Support\DocumentMergeDataBuilder built: the euro formatting
 * and the net/VAT split happened there, once, and a layout that recomputed
 * either would be a second opinion about the same figures.
 */
final class InvoiceDocument
{
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
    ) {}

    /** @param array<string, mixed> $mergeData */
    public static function forInvoice(PaymentInstallment $installment, array $mergeData, StudioSettingsResolver $settings): self
    {
        $invoice = $mergeData['invoice'] ?? [];
        $studio = $mergeData['studio'] ?? [];

        $payment = array_values(array_filter([
            $invoice['paymentTerm'] ?? '',
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
            ], fn (array $row) => $row[1] !== '')),
            description: $invoice['description'] ?? '',
            net: $invoice['net'] ?? '',
            vatRate: $invoice['vatRate'] ?? '',
            vat: $invoice['vat'] ?? '',
            gross: $invoice['gross'] ?? '',
            vatNote: $invoice['vatNote'] ?? '',
            smallBusiness: InvoiceFigures::forInstallment($installment, $settings)->rate === 0,
            sign: '',
            recipient: self::recipient($mergeData),
            paymentLines: $payment,
            reference: null,
        );
    }

    /** @param array<string, mixed> $mergeData */
    public static function forCreditNote(CreditNote $creditNote, array $mergeData, StudioSettingsResolver $settings): self
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
            ], fn (array $row) => $row[1] !== '')),
            description: $note['description'] ?? '',
            net: $note['net'] ?? '',
            vatRate: $note['vatRate'] ?? '',
            vat: $note['vat'] ?? '',
            gross: $note['gross'] ?? '',
            vatNote: $note['vatNote'] ?? '',
            smallBusiness: InvoiceFigures::forInstallment($creditNote->installment, $settings)->rate === 0,
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
     * Netto → Umsatzsteuer → Gesamtbetrag, or the single Gesamtbetrag a
     * Kleinunternehmer prints instead — § 19 UStG forbids showing a VAT line
     * at all, and `vatNote` carries the sentence that replaces it.
     *
     * @return list<array{0: string, 1: string, 2: bool}> label, amount, is-total
     */
    public function summaryRows(): array
    {
        if ($this->smallBusiness) {
            return [['Gesamtbetrag', $this->sign.$this->gross, true]];
        }

        return [
            ['Netto', $this->sign.$this->net, false],
            ['Umsatzsteuer '.$this->vatRate, $this->sign.$this->vat, false],
            ['Gesamtbetrag', $this->sign.$this->gross, true],
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
