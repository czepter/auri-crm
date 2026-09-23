<?php

namespace App\Integration\Data;

use App\Crm\Models\Task;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A task as I-03's task endpoints hand it to an api-surface token. public_id
 * only, for the task, its project, its shoot and whatever it depends on —
 * never a row's internal id.
 */
#[TypeScript]
class TaskReadData extends Data
{
    public function __construct(
        public string $id,
        public ?string $projectId,
        public ?string $contactId,
        public string $title,
        public string $audience,
        public ?string $dueOn,
        public bool $isDone,
        public ?string $completedOn,
        public ?string $shootId,
        public ?string $dependsOnTaskId,
        public bool $isBlocked,
        public ?string $assigneeId,
        public string $updatedAt,
    ) {}

    /** Requires `project:id,public_id`, `shoot`, `dependsOn` and `assignee` already eager loaded. */
    public static function fromModel(Task $task): self
    {
        return new self(
            id: $task->public_id,
            projectId: $task->case?->public_id,
            contactId: $task->contact?->public_id,
            title: $task->title,
            audience: $task->audience->value,
            dueOn: $task->due_on?->format('Y-m-d'),
            isDone: $task->isDone(),
            completedOn: $task->completed_at?->format('Y-m-d'),
            shootId: $task->shoot?->public_id,
            dependsOnTaskId: $task->dependsOn?->public_id,
            isBlocked: $task->isBlocked(),
            assigneeId: $task->assignee?->public_id,
            updatedAt: $task->updated_at->toIso8601String(),
        );
    }
}
