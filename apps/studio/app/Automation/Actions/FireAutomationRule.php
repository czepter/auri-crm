<?php

namespace App\Automation\Actions;

use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Notifications\NotificationService;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $recipient = $this->recipientFor($anchor);

        if ($recipient === null) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => 'No primary contact to notify.']);

            return $run;
        }

        try {
            $this->notifications->send($recipient, new AutomationReminder($rule, $this->settings));
            $run->update(['status' => AutomationRunStatus::Sent]);
        } catch (Throwable $e) {
            $run->update(['status' => AutomationRunStatus::Failed, 'error' => $e->getMessage()]);
        }

        return $run;
    }

    /** The anchor's own project's primary contact — a Shoot has one via ->project, a Project is one. */
    private function recipientFor(Model $anchor): ?Contact
    {
        $project = $anchor instanceof Shoot ? $anchor->project : $anchor;

        return $project instanceof Project ? $project->primaryContact() : null;
    }
}
