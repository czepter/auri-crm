<?php

namespace App\Payments\Actions;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of an existing plan's total and installments, past
 * CreatePaymentPlan's first write.
 *
 * Matched by public_id rather than replaced wholesale: an issued installment
 * carries its number, its PDF and its payment, and a delete-and-recreate
 * would drop all three (#407). A row the payload does not name is planning
 * and goes; an issued one cannot go at all, only be stornoed (#408).
 *
 * An issued row takes only its due_on and position from the payload — the
 * amount and title are refused by PaymentInstallment itself.
 *
 * One transaction around the total update and the row writes — required, not
 * just tidy, because the deferred constraint trigger on payment_installments
 * (2026_08_09_002500) checks at COMMIT, and a total written ahead of the rows
 * that match it would already violate "installments sum to total".
 */
class UpdatePaymentPlan
{
    /** @param  list<array{public_id?: ?string, amount: int, due_on: string, status?: string}>  $installments */
    public function __invoke(PaymentPlan $plan, int $total, array $installments): PaymentPlan
    {
        // #418: here rather than in the controller, so no second caller can
        // edit a plan that was settled.
        if ($plan->isSettled()) {
            throw ValidationException::withMessages(['installments' => __('payments.settleAlreadySettled')]);
        }

        DB::transaction(function () use ($plan, $total, $installments) {
            $plan->update(['total' => $total]);

            // Pulled, not read: what stays behind once the payload is walked
            // is exactly the set of rows it no longer names.
            $existing = $plan->installments()->get()->keyBy('public_id');

            foreach (array_values($installments) as $index => $payload) {
                $row = $existing->pull($payload['public_id'] ?? '');

                if ($row === null) {
                    $plan->installments()->create([
                        'amount' => $payload['amount'],
                        'due_on' => $payload['due_on'],
                        'status' => $payload['status'] ?? PaymentInstallmentStatus::Scheduled->value,
                        'sort_order' => $index,
                    ]);

                    continue;
                }

                $row->update($row->isInvoiced()
                    ? ['due_on' => $payload['due_on'], 'sort_order' => $index]
                    : [
                        'amount' => $payload['amount'],
                        'due_on' => $payload['due_on'],
                        'status' => $payload['status'] ?? $row->status->value,
                        'sort_order' => $index,
                    ]);
            }

            $existing->each(function (PaymentInstallment $row) {
                abort_if($row->isInvoiced(), 422, 'An issued installment cannot be removed from the plan.');

                $row->delete();
            });

            AuditLogger::record(
                event: 'payment_plan.updated',
                actor: auth('staff')->user(),
                context: ['total' => $total, 'installments' => count($installments)],
                subject: $plan->public_id,
                projectId: $plan->project_id,
            );
        });

        return $plan->fresh('installments');
    }
}
