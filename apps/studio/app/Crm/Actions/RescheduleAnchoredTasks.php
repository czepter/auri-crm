<?php

namespace App\Crm\Actions;

use App\Crm\Models\Project;
use App\Crm\Support\TaskAnchors;

/**
 * Writes due_on back onto every open anchored task of a project.
 *
 * Runs whenever an anchor could have moved — a stage entered, a status
 * entered, a shoot date changed. Materialising the day rather than resolving
 * it per read is what keeps the Übersicht, the project card and the counters
 * on one column they already know how to sort and filter.
 *
 * Completed tasks are left alone: what was owed and when is the record.
 */
class RescheduleAnchoredTasks
{
    public function __construct(private readonly TaskAnchors $anchors) {}

    public function __invoke(Project $project): void
    {
        $tasks = $project->tasks()->open()->whereNotNull('due_anchor')->get();

        foreach ($tasks as $task) {
            $dueOn = $this->anchors
                ->dueOn($project, $task->due_anchor, $task->due_offset_days ?? 0)
                ?->format('Y-m-d');

            if ($dueOn === $task->due_on?->format('Y-m-d')) {
                continue;
            }

            // T-19: a new deadline is a new deadline, so both ladders start
            // over. Only when the day actually moves — this runs on every
            // stage change, and resetting there would silence a task the
            // moment anything else on the project happened.
            $task->update([
                'due_on' => $dueOn,
                'reminders_sent' => 0,
                'client_reminders_sent' => 0,
            ]);
        }
    }
}
