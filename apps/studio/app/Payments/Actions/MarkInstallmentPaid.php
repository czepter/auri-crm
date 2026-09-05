<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of an installment's settlement — status, settled_via,
 * paid_on and payment_reference together, in one write, because a status
 * of `paid` with no record of how it happened is exactly the "chosen in
 * advance" shape T-07's issue rules out. Called with a real transfer or
 * manual receipt today; the same action, unchanged, is the one a future
 * Stripe webhook (M-04/M-05) is expected to call with SettledVia::Stripe.
 *
 * Idempotency guards the row, not the trigger — the same shape
 * App\Contracts\Actions\SignContract uses for a doubled signing request: an
 * installment already marked paid is returned as-is, with no second write
 * and no second InstallmentPaid dispatch, so two staff submissions (or one
 * retried request) racing for the same installment produce exactly one
 * event.
 */
class MarkInstallmentPaid
{
    public function __invoke(
        PaymentInstallment $installment,
        SettledVia $method,
        string $paidOn,
        ?string $reference,
        ?User $actor = null,
    ): PaymentInstallment {
        if ($installment->status === PaymentInstallmentStatus::Paid) {
            return $installment;
        }

        $installment->loadMissing('plan.job');

        DB::transaction(function () use ($installment, $method, $paidOn, $reference, $actor) {
            $installment->update([
                'status' => PaymentInstallmentStatus::Paid,
                'settled_via' => $method,
                'paid_on' => $paidOn,
                'payment_reference' => $reference,
            ]);

            AuditLogger::record(
                event: 'payment_installment.paid',
                actor: $actor,
                context: ['method' => $method->value, 'paid_on' => $paidOn, 'reference' => $reference],
                subject: $installment->public_id,
                projectId: $installment->plan->job->project_id,
            );
        });

        $installment = $installment->fresh();

        InstallmentPaid::dispatch($installment);

        return $installment;
    }
}
