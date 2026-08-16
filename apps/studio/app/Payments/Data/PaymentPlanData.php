<?php

namespace App\Payments\Data;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PaymentPlanData extends Data
{
    /** @param list<PaymentInstallmentData> $installments */
    public function __construct(
        public string $id,
        public int $total,
        // total less every already-paid installment — the "4.080 € offen"
        // chip in mockup 04-auftrag's Zahlungen card. Sent resolved rather
        // than left for the client to sum, the same reasoning Quote's own
        // data classes already send lineTotal and net.
        public int $openTotal,
        public bool $isEditable,
        // T-07's Verwendungszweck — the Invoice-type document number
        // CreatePaymentPlan allocates once, at creation (T-04's numbering).
        // Null for a plan created before this story's action ran.
        public ?string $reference,
        public array $installments,
    ) {}

    public static function fromModel(PaymentPlan $plan): self
    {
        $installments = $plan->installments;
        $count = $installments->count();

        $items = $installments->values()
            ->map(fn ($installment, int $index) => PaymentInstallmentData::fromModel(
                $installment,
                self::labelFor($index, $count),
            ))
            ->all();

        $openTotal = $plan->total - $installments
            ->where('status', PaymentInstallmentStatus::Paid)
            ->sum('amount');

        return new self(
            id: $plan->public_id,
            total: $plan->total,
            openTotal: $openTotal,
            isEditable: $plan->isEditable(),
            reference: $plan->reference,
            installments: $items,
        );
    }

    /**
     * The same label for one installment on its own — M-06's invoice
     * description needs "Anzahlung" or "2. Rate" and must not grow a second
     * copy of the rule that would drift from the plan screen's.
     */
    public static function labelForInstallment(PaymentInstallment $installment): string
    {
        $siblings = $installment->plan->installments()->orderBy('sort_order')->pluck('id')->all();
        $index = array_search($installment->id, $siblings, true);

        return self::labelFor($index === false ? 0 : (int) $index, count($siblings));
    }

    /** Anzahlung, first; Restbetrag, last; "N. Rate" in between — mockup 04-auftrag's own vocabulary. */
    private static function labelFor(int $index, int $count): string
    {
        return match (true) {
            $index === 0 => 'Anzahlung',
            $index === $count - 1 => 'Restbetrag',
            default => ($index + 1).'. Rate',
        };
    }
}
