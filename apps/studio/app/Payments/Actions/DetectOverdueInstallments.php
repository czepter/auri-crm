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
 *
 * M-05 gave the Stripe signal this class's own doc comment above already
 * asked for: App\Payments\Jobs\ProcessStripeWebhookEvent calls markOverdue()
 * below directly, for the one installment a checkout.session.
 * async_payment_failed event names, rather than this class growing a second
 * emission path — the guard is the same one-way flag either caller hits.
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
            $this->markOverdue($installment);
        }

        return $installments->count();
    }

    /**
     * One installment, guarded the same way the scan above filters its own
     * query: already paid, already flagged, or moved past `scheduled` some
     * other way is left untouched, so a Stripe failure signal arriving after
     * this row already has a different outcome writes nothing and dispatches
     * nothing.
     */
    public function markOverdue(PaymentInstallment $installment): void
    {
        if ($installment->status !== PaymentInstallmentStatus::Scheduled || $installment->overdue_notified_at !== null) {
            return;
        }

        $installment->update(['overdue_notified_at' => now('UTC')]);

        InstallmentOverdue::dispatch($installment);
    }
}
