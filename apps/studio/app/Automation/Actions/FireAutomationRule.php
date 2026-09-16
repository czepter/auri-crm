<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Automation\Support\AutomationConditions;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\Support\StudioAudience;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The one place an AutomationRule ever fires — both App\Automation\Actions\
 * SweepDateOffsetAutomationRules and App\Automation\Listeners\
 * FireAutomationRulesForEvent end here rather than sending a notification
 * themselves.
 *
 * The idempotency row is claimed FIRST, by a plain insert that
 * automation_runs' own unique constraint on (tenant_id, idempotency_key)
 * guards — the send only happens once that claim succeeds, so two
 * overlapping callers racing the same trigger (a redeployed scheduler
 * re-running the sweep, or a retried queued listener) can only ever produce
 * one AutomationRun and one send, never two. The upfront existence check is
 * purely an optimisation that skips building a notification for a trigger
 * already on record; the unique constraint below is what actually holds
 * under a genuine race.
 */
class FireAutomationRule
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioSettingsResolver $settings,
        private readonly StudioAudience $audience,
        private readonly AutomationConditions $conditions,
        private readonly DocumentMergeDataBuilder $merge,
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
        if (! $this->conditions->holds($rule->condition, $project)) {
            $run->update(['status' => AutomationRunStatus::Skipped]);

            return $run;
        }

        $recipients = $this->recipientsFor($rule, $project);

        if ($recipients->isEmpty()) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => 'No recipient to notify.']);

            return $run;
        }

        // E-15: resolved against the anchor's own project, so "morgen ist ein
        // Shooting" names which one. The claim above is already on record, so
        // a second run today sends nothing even if a colleague joined the crew
        // in between — the run answers "did this rule run", not "did everyone
        // get their mail".
        $data = $project === null ? [] : $this->merge->fromProject($project, $this->settings);

        try {
            foreach ($recipients as $recipient) {
                $this->notifications->send($recipient, new AutomationReminder($rule, $this->settings, $data));
            }

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
    private function recipientsFor(AutomationRule $rule, ?Project $project): Collection
    {
        if ($project === null) {
            return collect();
        }

        return match ($rule->recipient_type) {
            AutomationRecipient::Client => collect(array_filter([$project->primaryContact()])),
            // Alone in the studio nobody gets assigned; the one person is the crew.
            AutomationRecipient::Crew => User::soloStudio() ? $this->audience->forProject($project) : User::query()
                ->whereIn('id', CrewAssignment::query()
                    ->whereIn('shoot_id', $project->shoots()->select('id'))
                    ->select('user_id'))
                ->get(),
            AutomationRecipient::Studio => $this->audience->forProject($project),
        };
    }

    /** The project every anchor hangs off — a Project is one, the rest name theirs. */
    public static function projectOf(Model $anchor): ?Project
    {
        return match (true) {
            $anchor instanceof Project => $anchor,
            $anchor instanceof Shoot, $anchor instanceof Gallery,
            $anchor instanceof Task => $anchor->project,
            $anchor instanceof PaymentInstallment => $anchor->plan->job->project,
            default => null,
        };
    }
}
