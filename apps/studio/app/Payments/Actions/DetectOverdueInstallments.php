<?php

namespace App\Payments\Actions;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Models\PaymentInstallment;

/**
 * T-07's scheduled scan: every still-scheduled installment whose due date
 * has passed, provider-independent — a due date on the row itself is the
 * only input this reads. Any Stripe signal (a failed-payment webhook, once
 * I-04 exists) is meant to be an ADDITIONAL trigger for the same
 * InstallmentOverdue event, never a replacement for this scan; a
 * transfer-only studio must find its overdue installments exactly as
 * reliably as a Stripe-enabled one does.
 *
 * Runs daily from routes/console.php. `overdue_notified_at` is what keeps a
 * daily run from refiring the same event for the same still-unpaid
 * installment forever — set once, the moment this scan first notices it,
 * and never cleared.
 *
 * ponytail: a plain due_on < today scan, one query. Reminder cadence
 * (payments_reminders_enabled, "remind again after N days") is a distinct,
 * not-yet-scoped feature — add a separate scan reading that setting when a
 * story actually asks for repeat reminders rather than a one-time overdue
 * flag.
 */
class DetectOverdueInstallments
{
    public function __invoke(): int
    {
        $installments = PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereNull('overdue_notified_at')
            ->whereDate('due_on', '<', now('UTC')->toDateString())
            ->get();

        foreach ($installments as $installment) {
            $installment->update(['overdue_notified_at' => now('UTC')]);

            InstallmentOverdue::dispatch($installment);
        }

        return $installments->count();
    }
}
