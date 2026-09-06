<?php

namespace App\Notifications\Actions;

use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Models\Task;
use App\Models\User;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\NotificationService;
use App\Notifications\Studio;
use App\Notifications\Studio\MorningBriefing;
use App\Notifications\Support\NotificationPreferences;
use App\Notifications\Support\StudioAudience;
use App\Payments\Models\PaymentInstallment;
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

        if (! collect(NotificationMode::BRIEFABLE)->contains($briefed)) {
            return [];
        }

        $sections = [
            'Heute' => $this->shootsToday($user),
            'Fällig' => $briefed(Studio\TasksDue::class) ? $this->tasks($user) : [],
            'Liegengeblieben' => $briefed(Studio\LeadStalled::class) ? $this->leads($user) : [],
            'Offen' => $briefed(Studio\InstallmentOverdue::class) ? $this->installments($user) : [],
        ];

        return array_filter($sections);
    }

    /** @return list<string> */
    private function shootsToday(User $user): array
    {
        return Shoot::query()
            ->uncancelled()
            ->whereDate('date', now()->toDateString())
            ->with(['project', 'crew.user'])
            ->orderBy('call_time')
            ->get()
            ->filter(fn (Shoot $shoot) => $this->hears($user, $shoot->project))
            ->map(fn (Shoot $shoot) => trim(implode(' · ', array_filter([
                $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                $shoot->project->name,
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
            ->with(['project', 'assignee', 'dependsOn'])
            ->orderBy('due_on')
            ->get()
            ->reject(fn (Task $task) => $task->isBlocked())
            ->filter(fn (Task $task) => $this->audience->forTask($task)->contains('id', $user->id))
            ->map(fn (Task $task) => "{$task->project->name} — {$task->title} ({$task->due_on->format('d.m.')})")
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
            ->with('project')
            ->get()
            ->filter(fn (Lead $lead) => FibonacciReminderSchedule::reachedRungs($lead->stage_entered_at) > 0)
            ->filter(fn (Lead $lead) => $this->hears($user, $lead->project))
            ->map(fn (Lead $lead) => sprintf(
                '%s — %s, seit %d Tagen',
                $lead->project->name,
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
            ->with('plan.job.project')
            ->orderBy('due_on')
            ->get()
            ->filter(fn (PaymentInstallment $installment) => $this->hears($user, $installment->plan->job->project))
            ->map(fn (PaymentInstallment $installment) => sprintf(
                '%s — %s, fällig am %s',
                $installment->plan->job->project->name,
                number_format($installment->amount / 100, 2, ',', '.').' €',
                $installment->due_on->format('d.m.Y'),
            ))
            ->values()
            ->all();
    }

    private function hears(User $user, ?Project $project): bool
    {
        return $this->audience->forProject($project)->contains('id', $user->id);
    }
}
