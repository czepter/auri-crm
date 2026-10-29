<?php

namespace App\Crm\Actions;

use App\Crm\Models\Task;
use App\Models\User;

/** T-24: hand a task over, or take it back. */
class AssignTask
{
    public function __invoke(Task $task, ?string $assigneePublicId): Task
    {
        $task->update([
            'assignee_id' => $assigneePublicId === null
                ? null
                : User::query()->where('public_id', $assigneePublicId)->value('id'),
        ]);

        return $task;
    }
}
