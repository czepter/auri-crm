<?php

namespace App\Crm\Actions;

use App\Crm\Models\Task;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Completion is a record, not a delete: what was owed and when it was done
 * answers questions a removed row cannot.
 */
class CompleteTask
{
    /** $on is the day the work was actually done; null means now. */
    public function __invoke(Task $task, ?User $actor, ?string $on = null): Task
    {
        // S-12: a blocked task cannot be completed. Reopening one always can
        // — undoing a mistake must not itself be blocked by the thing the
        // mistake was about.
        if ($task->isBlocked()) {
            throw ValidationException::withMessages([
                'task' => "„{$task->dependsOn->title}“ ist noch offen.",
            ]);
        }

        $task->forceFill([
            'completed_at' => $on === null ? now() : CarbonImmutable::parse($on)->startOfDay(),
            'completed_by_id' => $actor?->id,
        ])->save();

        AuditLogger::record(
            event: 'task.completed',
            actor: $actor,
            context: ['title' => $task->title],
            subject: $task->public_id,
            projectId: $task->case_id,
        );

        return $task;
    }

    public function reopen(Task $task, ?User $actor): Task
    {
        $task->forceFill(['completed_at' => null, 'completed_by_id' => null])->save();

        AuditLogger::record(
            event: 'task.reopened',
            actor: $actor,
            context: ['title' => $task->title],
            subject: $task->public_id,
            projectId: $task->case_id,
        );

        return $task;
    }
}
