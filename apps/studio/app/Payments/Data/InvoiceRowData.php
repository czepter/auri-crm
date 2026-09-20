<?php

namespace App\Payments\Data;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** #480: one invoice as a line on /rechnungen. */
#[TypeScript]
class InvoiceRowData extends Data
{
    /**
     * @param  list<array{id: string, remaining: int}>  $openInstallments
     * @param  list<array{id: string, number: string, amount: int, issuedOn: string, hasDocument: bool}>  $creditNotes
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $issuedOn,
        public ?string $projectId,
        public ?string $projectName,
        public ?string $contact,
        public int $amount,
        public int $paidAmount,
        public int $credited,
        public ?string $dueOn,
        public string $status,
        public string $statusLabel,
        public ?string $sentOn,
        public ?string $viewedOn,
        public bool $hasDocument,
        public array $openInstallments,
        public array $creditNotes,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->public_id,
            number: $invoice->number,
            issuedOn: $invoice->issued_at->format('Y-m-d'),
            projectId: $invoice->project?->public_id,
            projectName: $invoice->project?->name,
            contact: $invoice->contact?->name,
            amount: $invoice->amount(),
            paidAmount: $invoice->paidAmount(),
            credited: $invoice->creditedAmount(),
            dueOn: $invoice->dueOn()?->format('Y-m-d'),
            status: $invoice->status(),
            statusLabel: __("payments.invoiceStatus.{$invoice->status()}"),
            sentOn: $invoice->sent_at?->format('Y-m-d'),
            viewedOn: $invoice->viewed_at?->format('Y-m-d'),
            hasDocument: $invoice->pdf_path !== null,
            openInstallments: $invoice->isCancelled() ? [] : $invoice->installments
                ->filter(fn (PaymentInstallment $row) => in_array($row->status, PaymentInstallmentStatus::open(), true))
                ->map(fn (PaymentInstallment $row) => ['id' => $row->public_id, 'remaining' => $row->remaining()])
                ->values()
                ->all(),
            creditNotes: $invoice->creditNotes->map(fn (CreditNote $note) => [
                'id' => $note->public_id,
                'number' => $note->number,
                'amount' => (int) $note->amount,
                'issuedOn' => $note->issued_at->format('Y-m-d'),
                'hasDocument' => $note->pdf_path !== null,
            ])->all(),
        );
    }
}
