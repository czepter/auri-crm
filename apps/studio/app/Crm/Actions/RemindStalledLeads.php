<?php

namespace App\Crm\Actions;

use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Stage;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\Studio\LeadStalled;
use App\Notifications\Support\StudioAudience;
use App\Support\FibonacciReminderSchedule;
use Illuminate\Support\Collection;

/**
 * T-20: the daily pass over inquiries nobody has touched.
 *
 * A lead is due when the Fibonacci ladder has reached a rung its counter has
 * not — see App\Support\FibonacciReminderSchedule for why the counter holds
 * rungs rather than a timestamp. A stage switched off keeps its leads out:
 * an abolished stage must not go on nagging.
 */
class RemindStalledLeads
{
    /** Last reminder on day 55; an inquiry parked for 2028 stops nagging after that. */
    public const CEILING_DAYS = 80;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
    ) {}

    /** @return int recipients notified */
    public function __invoke(): int
    {
        $due = Lead::query()
            ->open()
            ->unbooked()
            ->whereIn('stage', Stage::query()->pipeline(StagePipeline::Lead)->active()->pluck('key'))
            ->with('project')
            ->get()
            ->filter(fn (Lead $lead) => FibonacciReminderSchedule::isDue(
                $lead->stage_entered_at,
                $lead->stalled_reminders_sent,
                ceilingDays: self::CEILING_DAYS,
            ));

        if ($due->isEmpty()) {
            return 0;
        }

        $byRecipient = $this->group($due);

        foreach ($byRecipient as [$user, $leads]) {
            $this->notifications->send($user, new LeadStalled($leads));
        }

        foreach ($due as $lead) {
            $lead->forceFill([
                'stalled_reminders_sent' => FibonacciReminderSchedule::reachedRungs($lead->stage_entered_at, ceilingDays: self::CEILING_DAYS),
                'stalled_reminded_at' => now(),
            ])->save();
        }

        return count($byRecipient);
    }

    /**
     * One message per person, not per lead.
     *
     * @param  Collection<int, Lead>  $due
     * @return array<int, array{0: User, 1: Collection<int, Lead>}>
     */
    private function group(Collection $due): array
    {
        $recipients = [];

        foreach ($due as $lead) {
            foreach ($this->audience->forProject($lead->project) as $user) {
                $recipients[$user->id] ??= [$user, collect()];
                $recipients[$user->id][1]->push($lead);
            }
        }

        return array_values($recipients);
    }
}
