<?php

namespace App\Crm\Data;

use App\Crm\Models\Task;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A task as the Übersicht's worklist reads it: who it is about, what it is,
 * and when it was owed.
 */
#[TypeScript]
class TaskRowData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public string $title,
        public ?string $dueOn,
        public bool $isOverdue,
        public bool $isDone,
        public ?string $shootLabel,
        public string $audience,
        public string $audienceLabel,
        // S-12: waiting on something still open. Shown, never hidden — what
        // the studio is waiting for is exactly what it needs to see — and
        // named, because "blocked" without saying by what is a dead end.
        public bool $isBlocked = false,
        public ?string $blockedBy = null,
    ) {}

    public static function fromTask(Task $task): self
    {
        return new self(
            id: $task->public_id,
            projectId: $task->project->public_id,
            projectName: $task->project->name,
            title: $task->title,
            dueOn: $task->due_on?->format('Y-m-d'),
            // Today is not late. A task due today is the day's work, and
            // colouring it as overdue is how a worklist stops being read.
            isOverdue: $task->due_on !== null
                && ! $task->isDone()
                && $task->due_on->isBefore(now()->startOfDay()),
            isDone: $task->isDone(),
            isBlocked: $task->isBlocked(),
            blockedBy: $task->isBlocked() ? $task->dependsOn->title : null,
            // Which occasion it hangs off, so a project with four shoots can
            // tell its four briefings apart.
            shootLabel: $task->shoot?->label ?? $task->shoot?->kind->label(),
            audience: $task->audience->value,
            audienceLabel: $task->audience->label(),
        );
    }
}
