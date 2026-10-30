<?php

namespace App\Staff\Actions;

use App\Crm\Actions\RemindStalledLeads;
use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Models\Task;
use App\Crm\Notifications\Studio\LeadStalled;
use App\Crm\Notifications\Studio\TasksDue;
use App\Crm\Support\StudioAudience;
use App\Models\User;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\NotificationService;
use App\Notifications\Support\NotificationPreferences;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Notifications\Studio\InstallmentOverdue;
use App\Staff\Notifications\MorningBriefing;
use App\Staff\Support\StudioEvents;
use App\Support\FibonacciReminderSchedule;

/**
 * T-23: one morning mail per person, built from what is true today rather
 * than from what the 06:30 scans happened to send.
 *
 * The scans answer "is this worth saying again"; this answers "what is open".
 * The two differ on purpose — an installment stays in the briefing every day
 * it is unpaid, and reaches the summary once.
 *
 * "Heute" carries no preference row of its own. It is the one section with
 * something to say on a tidy day, and without it the mail is a list of what
 * the reader is behind on.
 */
class BuildMorningBriefing
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationPreferences $preferences,
        private readonly StudioAudience $audience,
    ) {}

    /** @return int mails sent */
    public function __invoke(): int
    {
        $sent = 0;

        foreach (User::query()->get() as $user) {
            $sections = $this->sectionsFor($user);

            if ($sections === []) {
                continue;
            }

            $this->notifications->send($user, new MorningBriefing($sections));
            $sent++;
        }

        return $sent;
    }

    /** @return array<string, list<string>> */
    private function sectionsFor(User $user): array
    {
        $briefed = fn (string $class) => $this->preferences->mode($user, $class) === NotificationMode::Briefing;

        if (! collect(StudioEvents::BRIEFABLE)->contains($briefed)) {
            return [];
        }

        $sections = [
            'Heute' => $this->shootsToday($user),
            'Fällig' => $briefed(TasksDue::class) ? $this->tasks($user) : [],
            'Liegengeblieben' => $briefed(LeadStalled::class) ? $this->leads($user) : [],
            'Offen' => $briefed(InstallmentOverdue::class) ? $this->installments($user) : [],
        ];

        return array_filter($sections);
    }

    /** @return list<string> */
    private function shootsToday(User $user): array
    {
        return Shoot::query()
            ->uncancelled()
            ->whereDate('date', now()->toDateString())
            ->with(['case', 'crew.user'])
            ->orderBy('call_time')
            ->get()
            ->filter(fn (Shoot $shoot) => $this->hears($user, $shoot->case))
            ->map(fn (Shoot $shoot) => trim(implode(' · ', array_filter([
                $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                $shoot->case->name,
                $shoot->venue_name ?? $shoot->venue_city,
                $shoot->crew->map(fn ($assignment) => $assignment->user->name)->implode(', ') ?: null,
            ]))))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function tasks(User $user): array
    {
        return Task::query()
            ->open()
            ->where('audience', TaskAudience::Internal)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', now()->toDateString())
            ->with(['case', 'contact', 'assignee', 'dependsOn'])
            ->orderBy('due_on')
            ->get()
            ->reject(fn (Task $task) => $task->isBlocked())
            ->filter(fn (Task $task) => $this->audience->forTask($task)->contains('id', $user->id))
            ->map(fn (Task $task) => "{$task->summary()} ({$task->due_on->format('d.m.Y')})")
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function leads(User $user): array
    {
        return Lead::query()
            ->open()
            ->unbooked()
            ->whereIn('stage', Stage::query()->pipeline(StagePipeline::Lead)->active()->pluck('key'))
            ->with('case')
            ->get()
            ->filter(fn (Lead $lead) => FibonacciReminderSchedule::reachedRungs($lead->stage_entered_at, ceilingDays: RemindStalledLeads::CEILING_DAYS) > 0)
            ->filter(fn (Lead $lead) => $this->hears($user, $lead->case))
            ->map(fn (Lead $lead) => sprintf(
                '%s — %s, seit %d Tagen',
                $lead->case->name,
                $lead->stage->name,
                (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            ))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function installments(User $user): array
    {
        return PaymentInstallment::query()
            ->overdue()
            ->with('plan.case')
            ->orderBy('due_on')
            ->get()
            ->filter(fn (PaymentInstallment $installment) => $this->hears($user, $installment->plan->case))
            ->map(fn (PaymentInstallment $installment) => sprintf(
                '%s — %s, fällig am %s',
                $installment->plan->case->name,
                number_format($installment->amount / 100, 2, ',', '.').' €',
                $installment->due_on->format('d.m.Y'),
            ))
            ->values()
            ->all();
    }

    private function hears(User $user, ?ClientCase $project): bool
    {
        return $this->audience->forCase($project)->contains('id', $user->id);
    }
}
