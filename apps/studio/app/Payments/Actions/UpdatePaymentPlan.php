<?php

namespace App\Payments\Actions;

use App\Payments\Models\PaymentPlan;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of an existing plan's total and installments, past
 * CreatePaymentPlan's first write. Wholesale replace, the same shape
 * App\Quotes\Http\Controllers\QuoteController::update() uses for quote
 * items: no id outside this plan references an installment row, so a
 * delete-and-recreate orphans nothing.
 *
 * One transaction around the total update, the delete and the recreate —
 * required, not just tidy, because the deferred constraint trigger on
 * payment_installments (2026_08_09_002500) checks at COMMIT. Split across
 * separate auto-committed statements, the delete alone would already violate
 * "installments sum to total" the instant it ran.
 */
class UpdatePaymentPlan
{
    /** @param  list<array{amount: int, due_on: string, status: string}>  $installments */
    public function __invoke(PaymentPlan $plan, int $total, array $installments): PaymentPlan
    {
        abort_unless($plan->isEditable(), 422, 'This payment plan is no longer editable.');

        DB::transaction(function () use ($plan, $total, $installments) {
            $plan->update(['total' => $total]);

            $plan->installments()->delete();
            $plan->installments()->createMany(
                collect($installments)->values()->map(fn (array $installment, int $index) => [
                    'amount' => $installment['amount'],
                    'due_on' => $installment['due_on'],
                    'status' => $installment['status'],
                    'sort_order' => $index,
                ])->all()
            );

            AuditLogger::record(
                event: 'payment_plan.updated',
                actor: auth('staff')->user(),
                context: ['total' => $total, 'installments' => count($installments)],
                subject: $plan->public_id,
            );
        });

        return $plan->fresh('installments');
    }
}
