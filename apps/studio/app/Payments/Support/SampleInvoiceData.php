<?php

namespace App\Payments\Support;

use App\Crm\Models\Contact;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;

/** An Anzahlungsrechnung that never touches the database, for the settings preview. */
class SampleInvoiceData
{
    private const TOTAL = 250000;

    public static function build(int $termDays, float $depositPercentage): Invoice
    {
        $item = new InvoiceItem([
            'title' => 'Anzahlung Fotoreportage ('.rtrim(rtrim(number_format($depositPercentage, 2, ',', ''), '0'), ',').' %)',
            'quantity' => 1,
            'unit_price' => (int) round(self::TOTAL * $depositPercentage / 100),
        ]);
        $items = collect([$item]);

        $invoice = new Invoice(['number' => '2026-014', 'issued_at' => now()]);
        $installment = new PaymentInstallment([
            'amount' => InvoiceFigures::forPositions($items, null, null)->gross,
            'due_on' => now()->addDays($termDays),
            'status' => PaymentInstallmentStatus::Invoiced,
        ]);

        return $invoice
            ->setRelation('creditNotes', collect())
            ->setRelation('template', null)
            ->setRelation('case', null)
            ->setRelation('contact', new Contact(['name' => 'Muster GmbH', 'street' => 'Musterstraße 1', 'postcode' => '12345', 'city' => 'Musterstadt']))
            ->setRelation('items', $items)
            ->setRelation('installments', collect([$installment]));
    }
}
