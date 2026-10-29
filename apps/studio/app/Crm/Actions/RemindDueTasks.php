<?php

namespace App\Crm\Actions;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Task;
use App\Crm\Notifications\Studio\TasksDue;
use App\Crm\Support\StudioAudience;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Portal\Notifications\TaskDue;
use App\Portal\Support\ClientAudience;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\FibonacciReminderSchedule;
use Illuminate\Support\Collection;

/**
 * T-19: the daily pass over what the studio owes itself.
 *
 * One rung before the sequence: the due date itself. The ladder counts days
 * of lateness, and a task due today is due today — after that 3, 5, 8, 13,
 * 21 days past due_on, the same ladder the inquiries climb.
 *
 * Blocked tasks stay out. `depends_on_task_id` pointing at something still
 * open means this one cannot be done, and a reminder to do it asks for
 * something impossible.
 *
 * T-28 hangs its second pass off the same run: the client's tasks, on the
 * same ladder one rung later, with a counter of their own.
 */
class RemindDueTasks
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StudioAudience $audience,
        private readonly ClientAudience $clients,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return int recipients notified */
    public function __invoke(): int
    {
        $this->remindClients();

        $due = self::dueToday();

        if ($due->isEmpty()) {
            return 0;
        }

        $recipients = $this->group($due);

        foreach ($recipients as [$user, $tasks]) {
            $this->notifications->send($user, new TasksDue($tasks));
        }

        foreach ($due as $task) {
            $task->forceFill(['reminders_sent' => self::rungs($task)])->save();
        }

        return count($recipients);
    }

    /**
     * The open, unblocked, internal tasks whose ladder has reached a rung
     * their counter has not. Also what the morning briefing (T-23) lists,
     * which is why it is public and static rather than folded into the loop.
     *
     * @return Collection<int, Task>
     */
    public static function dueToday(): Collection
    {
        return Task::query()
            ->open()
            ->where('audience', TaskAudience::Internal)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', now()->toDateString())
            ->with(['case', 'contact', 'assignee', 'dependsOn'])
            // Overdue first, then today's — the order the mail is read in.
            ->orderBy('due_on')
            ->get()
            ->reject(fn (Task $task) => $task->isBlocked())
            ->filter(fn (Task $task) => self::rungs($task) > $task->reminders_sent)
            ->values();
    }

    /**
     * T-28: the second pass, over the tasks the client owes. One rung behind
     * the studio's — not because anything is in flight, but because the third
     * mail in five days arrives as nagging.
     *
     * Nothing goes out while the portal is closed: the link would land on the
     * closure page.
     */
    private function remindClients(): void
    {
        if ($this->settings->maintenanceEnabled()) {
            return;
        }

        $due = Task::query()
            ->open()
            ->where('audience', TaskAudience::Client)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', now()->toDateString())
            ->with('case')
            ->orderBy('due_on')
            ->get()
            ->reject(fn (Task $task) => $task->isBlocked())
            ->filter(fn (Task $task) => FibonacciReminderSchedule::isDue(
                $task->due_on->toImmutable(),
                $task->client_reminders_sent,
            ));

        foreach ($due as $task) {
            $recipients = $this->clients->forCase($task->case);

            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($recipients as $contact) {
                $this->notifications->send($contact, new TaskDue($task, $this->settings));
            }

            $task->forceFill([
                'client_reminders_sent' => FibonacciReminderSchedule::reachedRungs($task->due_on->toImmutable()),
            ])->save();
        }
    }

    /** The due day itself, plus every Fibonacci day of lateness since. */
    private static function rungs(Task $task): int
    {
        return 1 + FibonacciReminderSchedule::reachedRungs($task->due_on->toImmutable());
    }

    /**
     * @param  Collection<int, Task>  $due
     * @return array<int, array{0: User, 1: Collection<int, Task>}>
     */
    private function group(Collection $due): array
    {
        $recipients = [];

        foreach ($due as $task) {
            foreach ($this->audience->forTask($task) as $user) {
                $recipients[$user->id] ??= [$user, collect()];
                $recipients[$user->id][1]->push($task);
            }
        }

        return array_values($recipients);
    }
}
