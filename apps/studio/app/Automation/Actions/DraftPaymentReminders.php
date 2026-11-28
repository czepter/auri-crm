<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRun;
use App\Models\StudioSettings;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Notifications\PaymentReminder;
use App\Portal\Support\ClientAudience;
use App\Support\FibonacciReminderSchedule;

/**
 * T-25: the client's own ladder, one rung behind the studio's. A day late
 * is a transfer in flight; the studio may know that earlier and more
 * often than the client is asked about it. The reminder waits in the
 * Posteingang as a draft: nothing reaches a client without the studio.
 *
 * Nothing is drafted while the portal is closed for maintenance — the link
 * would land on the closure page.
 */
class DraftPaymentReminders
{
    public function __construct(private readonly ClientAudience $clients) {}

    public function __invoke(): void
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

            $open = AutomationRun::query()
                ->whereMorphedTo('anchorable', $installment)
                ->where('status', AutomationRunStatus::AwaitingApproval)
                ->get();

            foreach ($recipients as $contact) {
                $draft = (new PaymentReminder($installment))->draft($contact);
                $waiting = $open->first(fn (AutomationRun $run) => $run->contact_ids === [$contact->id]);

                if ($waiting !== null) {
                    $waiting->update(['subject' => $draft['subject'], 'body' => $draft['body']]);

                    continue;
                }

                AutomationRun::query()->firstOrCreate(
                    ['idempotency_key' => hash('sha256', implode('|', ['payment-reminder', $installment->getKey(), $contact->getKey(), now('UTC')->toDateString()]))],
                    [
                        'anchorable_type' => $installment->getMorphClass(),
                        'anchorable_id' => $installment->getKey(),
                        'fired_at' => now('UTC'),
                        'status' => AutomationRunStatus::AwaitingApproval,
                        'subject' => $draft['subject'],
                        'body' => $draft['body'],
                        'contact_ids' => [$contact->id],
                    ],
                );
            }

            $installment->forceFill([
                'client_reminders_sent' => FibonacciReminderSchedule::reachedRungs($installment->due_on->toImmutable()),
            ])->save();
        }
    }
}
