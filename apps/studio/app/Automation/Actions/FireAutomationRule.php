<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Automation\Support\AutomationConditions;
use App\Crm\Actions\CreateTask;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Support\StudioAudience;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MergeFieldResolver;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceMergeData;
use App\Portal\Support\ClientAudience;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The one place an AutomationRule ever fires — both App\Automation\Actions\
 * SweepDateOffsetAutomationRules and App\Automation\Listeners\
 * FireAutomationRulesForEvent end here rather than acting themselves.
 *
 * The idempotency row is claimed FIRST, by a plain insert that
 * automation_runs' own unique constraint on (tenant_id, idempotency_key)
 * guards — the action only happens once that claim succeeds, so two
 * overlapping callers racing the same trigger (a redeployed scheduler
 * re-running the sweep, or a retried queued listener) can only ever produce
 * one AutomationRun, never two. The upfront existence check is purely an
 * optimisation; the unique constraint is what holds under a genuine race.
 *
 * #438: a client never receives anything from here. Their mail stops on the
 * run as a draft (AwaitingApproval) until the studio sends it from the
 * Posteingang; only the studio side is notified or handed a task directly.
 */
class FireAutomationRule
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
        private readonly ClientAudience $clients,
        private readonly AutomationConditions $conditions,
        private readonly DocumentMergeData $merge,
        private readonly InvoiceMergeData $invoiceMerge,
        private readonly CreateTask $createTask,
    ) {}

    public function __invoke(AutomationRule $rule, Model $anchor, string $triggerDate): ?AutomationRun
    {
        $key = AutomationRun::idempotencyKey($rule, $anchor, $triggerDate);

        if (AutomationRun::query()->where('idempotency_key', $key)->exists()) {
            return null;
        }

        try {
            $run = AutomationRun::create([
                'automation_rule_id' => $rule->getKey(),
                'anchorable_type' => $anchor->getMorphClass(),
                'anchorable_id' => $anchor->getKey(),
                'idempotency_key' => $key,
                'fired_at' => now('UTC'),
                'status' => AutomationRunStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another caller claimed this trigger first.
            return null;
        }

        $project = self::projectOf($anchor);

        // E-15: the rule worked, it just had nothing to say. Its own state,
        // so the history answers "why did nothing arrive".
        if (! $this->conditions->holds($rule->condition, $project)
            || ($rule->project_type !== null && $project?->type?->key !== $rule->project_type)) {
            $run->update(['status' => AutomationRunStatus::Skipped]);

            return $run;
        }

        // E-15: resolved against the anchor's own project, so "morgen ist ein
        // Shooting" names which one.
        $invoice = $anchor instanceof PaymentInstallment ? $anchor->invoice : null;
        $data = match (true) {
            $project === null => [],
            $invoice !== null => $this->invoiceMerge->forInvoice($invoice),
            default => $this->merge->forCase($project),
        };
        $subject = MergeFieldResolver::resolve($rule->notification_subject, $data, keepUnknown: true);
        $body = MergeFieldResolver::resolve($rule->notification_body, $data, keepUnknown: true);

        if ($rule->action === AutomationAction::Task) {
            return $this->task($run, $rule, $project, $anchor, $triggerDate, $subject);
        }

        $recipients = $this->recipientsFor($rule, $project);

        if ($recipients->isEmpty()) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => 'No recipient to notify.']);

            return $run;
        }

        if ($rule->action === AutomationAction::MailDraft) {
            $run->update([
                'status' => AutomationRunStatus::AwaitingApproval,
                'subject' => $subject,
                'body' => $body,
                'contact_ids' => $recipients->pluck('id')->all(),
            ]);

            return $run;
        }

        // The claim above is already on record, so a second run today sends
        // nothing even if a colleague joined the crew in between.
        try {
            foreach ($recipients as $recipient) {
                $this->notifications->send($recipient, new AutomationReminder($subject, $body));
            }

            $run->update(['status' => AutomationRunStatus::Sent]);
        } catch (Throwable $e) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => $e->getMessage()]);
        }

        return $run;
    }

    /**
     * An internal task on the anchor's project. The trigger date is the
     * anchor's own date, so the offset lands the task on the day the rule
     * fires; an event rule has no offset and lands on its day.
     */
    private function task(AutomationRun $run, AutomationRule $rule, ?ClientCase $project, Model $anchor, string $triggerDate, string $title): AutomationRun
    {
        $date = CarbonImmutable::parse($triggerDate);
        $offset = $rule->offset_days ?? 0;

        try {
            ($this->createTask)([
                'case_id' => $project?->id,
                'shoot_id' => $anchor instanceof Shoot ? $anchor->id : null,
                'title' => $title,
                'audience' => TaskAudience::Internal,
                'due_on' => $rule->direction === AutomationOffsetDirection::Before ? $date->subDays($offset) : $date->addDays($offset),
            ]);

            $run->update(['status' => AutomationRunStatus::Sent]);
        } catch (Throwable $e) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => $e->getMessage()]);
        }

        return $run;
    }

    /**
     * E-14: the client as before, or the studio side. `crew` stays silent
     * where nobody is assigned — "Ausrüstung packen" has no meaning for the
     * whole studio — while `studio` takes StudioAudience's own widening.
     *
     * @return Collection<int, Model>
     */
    private function recipientsFor(AutomationRule $rule, ?ClientCase $project): Collection
    {
        if ($project === null) {
            return collect();
        }

        return match ($rule->recipient_type) {
            AutomationRecipient::Client => $this->clients->forCase($project),
            // Alone in the studio nobody gets assigned; the one person is the crew.
            AutomationRecipient::Crew => User::soloStudio() ? $this->audience->forCase($project) : User::query()
                ->whereIn('id', CrewAssignment::query()
                    ->whereIn('shoot_id', $project->shoots()->select('id'))
                    ->select('user_id'))
                ->get(),
            AutomationRecipient::Studio => $this->audience->forCase($project),
        };
    }

    /** The project every anchor hangs off — a ClientCase is one, the rest name theirs. */
    public static function projectOf(Model $anchor): ?ClientCase
    {
        return match (true) {
            $anchor instanceof ClientCase => $anchor,
            $anchor instanceof Shoot, $anchor instanceof Gallery,
            $anchor instanceof Task => $anchor->case,
            $anchor instanceof PaymentInstallment => $anchor->case(),
            default => null,
        };
    }
}
