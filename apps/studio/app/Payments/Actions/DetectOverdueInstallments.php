<?php

namespace App\Payments\Actions;

use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\Studio;
use App\Notifications\Support\StudioAudience;
use App\Payments\Enums\OverdueReason;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Models\PaymentInstallment;
use App\Support\FibonacciReminderSchedule;
use Illuminate\Support\Collection;

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
 * T-22 answers the cadence this class left open. A second pass runs over the
 * installments already flagged and still unpaid, and the Fibonacci ladder
 * (App\Support\FibonacciReminderSchedule, anchored on due_on) decides which
 * of them are worth saying again — so the rhythm is in the sequence and not
 * in a setting nobody maintains. A row that leaves `scheduled`/`invoiced`
 * falls out of the query and needs no reset.
 *
 * M-05 gave the Stripe signal this class's own doc comment above already
 * asked for: App\Payments\Jobs\ProcessStripeWebhookEvent calls markOverdue()
 * below directly, for the one installment a checkout.session.
 * async_payment_failed event names, rather than this class growing a second
 * emission path — the guard is the same one-way flag either caller hits.
 */
class DetectOverdueInstallments
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
    ) {}

    /** @return int installments newly flagged */
    public function __invoke(): int
    {
        // `invoiced` counts as well as `scheduled`: an issued, unpaid invoice
        // is the case arrears actually matter in. Nothing writes that status
        // today (see PaymentPlan), which makes the clause inert rather than
        // wrong — and closes the trap before invoicing sets it.
        $installments = PaymentInstallment::query()
            ->whereIn('status', [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced])
            ->whereNull('overdue_notified_at')
            ->whereDate('due_on', '<', now('UTC')->toDateString())
            ->get();

        foreach ($installments as $installment) {
            $this->markOverdue($installment);
        }

        $this->remindStudio();

        return $installments->count();
    }

    /**
     * The second pass: everything already flagged and still owed, on the rung
     * the ladder has reached. One message per recipient — three overdue
     * installments are one occasion.
     */
    private function remindStudio(): void
    {
        $due = PaymentInstallment::query()
            ->overdue()
            ->with('plan.job.project')
            ->get()
            ->filter(fn (PaymentInstallment $installment) => FibonacciReminderSchedule::isDue(
                $installment->due_on->toImmutable(),
                $installment->overdue_reminders_sent,
            ));

        if ($due->isEmpty()) {
            return;
        }

        foreach ($this->group($due) as [$user, $installments]) {
            $this->notifications->send($user, new Studio\InstallmentOverdue($installments));
        }

        foreach ($due as $installment) {
            $installment->forceFill([
                'overdue_reminders_sent' => FibonacciReminderSchedule::reachedRungs($installment->due_on->toImmutable()),
            ])->save();
        }
    }

    /**
     * @param  Collection<int, PaymentInstallment>  $due
     * @return array<int, array{0: User, 1: Collection<int, PaymentInstallment>}>
     */
    private function group(Collection $due): array
    {
        $recipients = [];

        foreach ($due as $installment) {
            foreach ($this->audience->forProject($installment->plan->job->project) as $user) {
                $recipients[$user->id] ??= [$user, collect()];
                $recipients[$user->id][1]->push($installment);
            }
        }

        return array_values($recipients);
    }

    /**
     * One installment, guarded the same way the scan above filters its own
     * query: already paid, already flagged, or moved past `scheduled` some
     * other way is left untouched, so a Stripe failure signal arriving after
     * this row already has a different outcome writes nothing and dispatches
     * nothing.
     */
    public function markOverdue(
        PaymentInstallment $installment,
        OverdueReason $reason = OverdueReason::DueDatePassed,
    ): void {
        $open = [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced];

        if (! in_array($installment->status, $open, true) || $installment->overdue_notified_at !== null) {
            return;
        }

        $installment->update([
            'overdue_notified_at' => now('UTC'),
            'overdue_reason' => $reason,
            // The first word goes out through the event below; the ladder
            // counts from there, so the next rung is a repeat and not a echo.
            'overdue_reminders_sent' => FibonacciReminderSchedule::reachedRungs($installment->due_on->toImmutable()),
        ]);

        InstallmentOverdue::dispatch($installment);
    }
}
