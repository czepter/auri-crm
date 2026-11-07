<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only writer of an installment's settlement — status, settled_via,
 * paid_on and payment_reference together, in one write, because a status
 * of `paid` with no record of how it happened is exactly the "chosen in
 * advance" shape T-07's issue rules out. Called with a real transfer or
 * manual receipt today; the same action, unchanged, is the one a future
 * Stripe webhook (M-04/M-05) is expected to call with SettledVia::Stripe.
 *
 * Idempotency guards the row, not the trigger: an
 * installment already marked paid is returned as-is, with no second write
 * and no second InstallmentPaid dispatch, so two staff submissions (or one
 * retried request) racing for the same installment produce exactly one
 * event.
 *
 * #414: a transfer that comes up short books as far as it goes. Amounts add
 * up across calls, so the caller passes what arrived and never a running
 * total. InstallmentPaid fires only once the row is fully settled.
 */
class MarkInstallmentPaid
{
    public function __invoke(
        PaymentInstallment $installment,
        SettledVia $method,
        string $paidOn,
        ?string $reference,
        ?User $actor = null,
        ?int $amount = null,
    ): PaymentInstallment {
        if ($installment->status === PaymentInstallmentStatus::Paid) {
            return $installment;
        }

        if ($installment->status === PaymentInstallmentStatus::Cancelled) {
            report(new RuntimeException(sprintf(
                'Zahlung für stornierte Rate %s eingegangen (Session %s, Betrag %s).',
                $installment->public_id,
                $installment->stripe_checkout_session_id ?? '-',
                $amount ?? $installment->remaining(),
            )));

            return $installment;
        }

        $booked = $amount ?? $installment->remaining();
        $paidAmount = (int) $installment->paid_amount + $booked;
        $status = $paidAmount >= (int) $installment->amount
            ? PaymentInstallmentStatus::Paid
            : PaymentInstallmentStatus::Partial;

        $recorded = DB::transaction(function () use ($installment, $method, $paidOn, $reference, $actor, $booked, $paidAmount, $status) {
            // Webhook and success redirect race for the same rate; the loser must not log a second payment.
            $current = $installment->newQuery()->lockForUpdate()->find($installment->id);

            if ($current === null || $current->status !== $installment->status || (int) $current->paid_amount !== (int) $installment->paid_amount) {
                return false;
            }

            $installment->update([
                'status' => $status,
                'settled_via' => $method,
                'paid_on' => $paidOn,
                'payment_reference' => $reference,
                'paid_amount' => $paidAmount,
            ]);

            AuditLogger::record(
                event: 'payment_installment.paid',
                actor: $actor,
                context: [
                    'method' => $method->value,
                    'paid_on' => $paidOn,
                    'reference' => $reference,
                    'amount' => $booked,
                    'remaining' => max(0, (int) $installment->amount - $paidAmount),
                ],
                subject: $installment->public_id,
                projectId: $installment->case()?->id,
            );

            return true;
        });

        if (! $recorded) {
            return $installment->fresh();
        }

        $installment = $installment->fresh();

        if ($status === PaymentInstallmentStatus::Paid) {
            InstallmentPaid::dispatch($installment);
        }

        return $installment;
    }
}
