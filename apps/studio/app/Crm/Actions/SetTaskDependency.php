<?php

namespace App\Crm\Actions;

use App\Crm\Models\Task;
use App\Crm\Support\TaskDependencyGraph;
use Illuminate\Validation\ValidationException;

/**
 * S-12: change what a task waits on. A cycle is refused here, at write
 * time — see TaskDependencyGraph for why discovering one at read time is
 * worse than refusing the save.
 */
class SetTaskDependency
{
    public function __invoke(Task $task, ?string $dependsOn): Task
    {
        if (blank($dependsOn)) {
            $task->update(['depends_on_task_id' => null]);

            return $task;
        }

        $predecessor = Task::query()
            ->where('case_id', $task->case_id)
            ->where('public_id', $dependsOn)
            ->first();

        abort_if($predecessor === null, 422);

        if (TaskDependencyGraph::wouldCycle($task, $predecessor)) {
            throw ValidationException::withMessages([
                'depends_on' => "„{$predecessor->title}“ wartet selbst auf diese Aufgabe.",
            ]);
        }

        $task->update(['depends_on_task_id' => $predecessor->id]);

        return $task;
    }
}
