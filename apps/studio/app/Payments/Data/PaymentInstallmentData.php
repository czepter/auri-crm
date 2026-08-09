<?php

namespace App\Payments\Data;

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
        );
    }
}
