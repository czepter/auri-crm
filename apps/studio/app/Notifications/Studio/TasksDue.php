<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
use App\Crm\Models\Task;
use Illuminate\Support\Collection;

/**
 * T-19: what is owed today and what was owed earlier. One message per
 * recipient, overdue first — the order is the answer to "where do I start".
 *
 * @param  Collection<int, Task>  $tasks
 */
class TasksDue extends StudioEventNotification
{
    public function __construct(private readonly Collection $tasks) {}

    public function title(): string
    {
        return 'Fällige Aufgaben';
    }

    public function body(): string
    {
        return implode(' · ', $this->lines());
    }

    /** @return list<string> */
    public function lines(): array
    {
        $today = now()->startOfDay();

        return $this->tasks
            ->map(fn (Task $task) => $task->due_on->lt($today)
                ? sprintf('%s — %s, seit %d Tagen überfällig', $task->project->name, $task->title, (int) $task->due_on->diffInDays($today))
                : sprintf('%s — %s, heute fällig', $task->project->name, $task->title))
            ->values()
            ->all();
    }

    public function url(): string
    {
        return '/uebersicht';
    }

    /* Several projects at once — the scan resolved the recipients per task. */
    public function project(): ?Project
    {
        return null;
    }
}
