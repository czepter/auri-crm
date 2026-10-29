<?php

namespace App\Portal\Actions;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Task;
use App\Notifications\NotificationService;
use App\Portal\Notifications\TaskDue;
use App\Portal\Support\ClientAudience;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\FibonacciReminderSchedule;

class RemindClientTasks
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ClientAudience $clients,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /**
     * T-28: the second pass, over the tasks the client owes. One rung behind
     * the studio's — not because anything is in flight, but because the third
     * mail in five days arrives as nagging.
     *
     * Nothing goes out while the portal is closed: the link would land on the
     * closure page.
     */
    public function __invoke(): void
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
}
