<?php

namespace App\Payments\Actions;

use App\Crm\Support\StudioAudience;
use App\Models\StudioSettings;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Payments\Enums\OverdueReason;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Notifications\PaymentReminder;
use App\Payments\Notifications\Studio;
use App\Portal\Support\ClientAudience;
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
    private const DUE_SOON_DAYS = 3;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
        private readonly ClientAudience $clients,
    ) {}

    /** @return int installments newly flagged */
    public function __invoke(): int
    {
        // `invoiced` counts as well as `scheduled`: an issued, unpaid invoice
        // is the case arrears actually matter in. Nothing writes that status
        // today (see PaymentPlan), which makes the clause inert rather than
        // wrong — and closes the trap before invoicing sets it.
        $installments = PaymentInstallment::query()
            ->whereIn('status', PaymentInstallmentStatus::open())
            ->whereNull('overdue_notified_at')
            ->whereDate('due_on', '<', now('UTC')->toDateString())
            ->onLivePlan()
            ->get();

        foreach ($installments as $installment) {
            $this->markOverdue($installment);
        }

        $this->announceDueSoon();
        $this->remindStudio();
        $this->remindClients();

        return $installments->count();
    }

    /**
     * #417: three days out, and only for an invoice the client has actually
     * been handed — a `scheduled` row is a date in a plan, not a demand
     * anyone has to act on yet. Stamped rather than counted: one rung.
     *
     * The window is a range and not the single date `due_on - 3`, because a
     * day the scheduler missed would otherwise skip the rung entirely. Rows
     * already past due belong to the ladder below.
     */
    private function announceDueSoon(): void
    {
        $today = now('UTC')->toDateString();

        $soon = PaymentInstallment::query()
            ->whereIn('status', [PaymentInstallmentStatus::Invoiced, PaymentInstallmentStatus::Partial])
            ->whereNull('before_due_reminder_sent_at')
            ->whereDate('due_on', '>=', $today)
            ->whereDate('due_on', '<=', now('UTC')->addDays(self::DUE_SOON_DAYS)->toDateString())
            ->with('plan.case')
            ->get();

        if ($soon->isEmpty()) {
            return;
        }

        foreach ($this->group($soon) as [$user, $installments]) {
            $this->notifications->send($user, new Studio\InstallmentDueSoon($installments));
        }

        foreach ($soon as $installment) {
            $installment->forceFill(['before_due_reminder_sent_at' => now('UTC')])->save();
        }
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
            ->with('plan.case')
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
     * T-25: the client's own ladder, one rung behind the studio's. A day late
     * is a transfer in flight; the studio may know that earlier and more
     * often than the client is asked about it.
     *
     * Nothing goes out while the portal is closed for maintenance — the link
     * would land on the closure page.
     */
    private function remindClients(): void
    {
        if (! (bool) StudioSettings::current()->payments_reminders_enabled || (bool) StudioSettings::current()->portal_maintenance_enabled) {
            return;
        }

        $due = PaymentInstallment::query()
            ->overdue()
            ->with('plan.case')
            ->get()
            ->filter(fn (PaymentInstallment $installment) => FibonacciReminderSchedule::isDue(
                $installment->due_on->toImmutable(),
                $installment->client_reminders_sent,
            ));

        foreach ($due as $installment) {
            $recipients = $this->clients->forCase($installment->plan->case);

            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($recipients as $contact) {
                $this->notifications->send($contact, new PaymentReminder($installment));
            }

            $installment->forceFill([
                'client_reminders_sent' => FibonacciReminderSchedule::reachedRungs($installment->due_on->toImmutable()),
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
            foreach ($this->audience->forCase($installment->plan->case) as $user) {
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
        if (! in_array($installment->status, PaymentInstallmentStatus::open(), true) || $installment->overdue_notified_at !== null) {
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
