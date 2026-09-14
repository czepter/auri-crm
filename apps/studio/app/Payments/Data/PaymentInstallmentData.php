<?php

namespace App\Payments\Data;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PaymentInstallmentData extends Data
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $dueOn,
        public string $status,
        public string $statusLabel,
        public int $sortOrder,
        // Mockup 04-auftrag's "Anzahlung" / "2. Rate" / "Restbetrag" — derived
        // from position by PaymentPlanData::fromModel() rather than a stored
        // column. Sent alongside the raw fields rather than left for the
        // client to compute, the same reasoning QuoteItemData sends
        // isIncluded and lineTotal already resolved.
        public string $label,
        // T-07: how, when and under what reference this installment was
        // actually settled — null until App\Payments\Actions\
        // MarkInstallmentPaid writes all three together. Never a Stripe key
        // or anything else T-07 keeps out of a response body.
        public ?string $settledVia,
        public ?string $settledViaLabel,
        public ?string $paidOn,
        public ?string $paymentReference,
        // Derived, not stored: the same "still scheduled, due date passed"
        // condition App\Payments\Actions\DetectOverdueInstallments queries
        // for its own scan, minus that action's overdue_notified_at guard —
        // that column only dedupes the one-time notification event and is
        // never a second source of truth for whether a row is late.
        public bool $isLate,
        // M-06: the issued invoice. `invoiceNumber` is also the Verwendungszweck
        // the client transfers under — T-07's plan-level reference stopped
        // being allocated once real invoice numbers existed, because every
        // one it drew left a hole in the invoice sequence.
        // `hasInvoiceDocument` is false between issue and render: the number
        // exists the moment the action runs, the PDF only after Gotenberg
        // returns, and a download link offered in that window would 404.
        public ?string $invoiceNumber,
        public ?string $invoicedOn,
        public bool $hasInvoiceDocument,
        // #409: every Gutschrift written against this rate. `hasDocument` is
        // false between issue and render, for the same reason
        // hasInvoiceDocument is.
        /** @var list<array{id: string, number: string, amount: int, issuedOn: string, hasDocument: bool}> */
        public array $creditNotes,
    ) {}

    /**
     * $label comes from the caller (PaymentPlanData::fromModel) rather than
     * being derived here — naming "Anzahlung" versus "Restbetrag" needs the
     * whole plan's installment count, which one row alone does not have.
     */
    public static function fromModel(PaymentInstallment $installment, string $label): self
    {
        return new self(
            id: $installment->public_id,
            amount: $installment->amount,
            dueOn: $installment->due_on->format('Y-m-d'),
            status: $installment->status->value,
            statusLabel: $installment->status->label(),
            sortOrder: $installment->sort_order,
            label: $label,
            settledVia: $installment->settled_via?->value,
            settledViaLabel: $installment->settled_via?->label(),
            paidOn: $installment->paid_on?->format('Y-m-d'),
            paymentReference: $installment->payment_reference,
            isLate: $installment->status === PaymentInstallmentStatus::Scheduled
                && $installment->due_on->lt(now('UTC')->startOfDay()),
            invoiceNumber: $installment->invoice_number,
            invoicedOn: $installment->invoiced_at?->format('Y-m-d'),
            hasInvoiceDocument: $installment->invoice_path !== null,
            creditNotes: $installment->creditNotes->map(fn (CreditNote $note) => [
                'id' => $note->public_id,
                'number' => $note->number,
                'amount' => $note->amount,
                'issuedOn' => $note->issued_at->format('Y-m-d'),
                'hasDocument' => $note->pdf_path !== null,
            ])->all(),
        );
    }
}
