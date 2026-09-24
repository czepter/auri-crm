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
        public ?string $projectId,
        public ?string $projectName,
        public string $title,
        public ?string $dueOn,
        public bool $isOverdue,
        public bool $isDone,
        public ?string $shootId,
        public ?string $shootLabel,
        public string $audience,
        public string $audienceLabel,
        // S-12: waiting on something still open. Shown, never hidden — what
        // the studio is waiting for is exactly what it needs to see — and
        // named, because "blocked" without saying by what is a dead end.
        public bool $isBlocked = false,
        public ?string $blockedBy = null,
        // Erledigtes steht zusammengefaltet mit seinem Tag: das ist die Spur,
        // nach der gesucht wird, und ein Häkchen ohne Datum ist keine.
        public ?string $completedOn = null,
        // Woran die Fälligkeit hängt. Steht anstelle des Datums, solange der
        // Anker noch nicht eingetreten ist.
        public ?string $dueAnchorLabel = null,
        // T-24: wer sie schuldet. Leer heißt Crew, sonst Studio — die
        // Auflösung steht in StudioAudience::forTask().
        public ?string $assigneeId = null,
        public ?string $assigneeName = null,
        // Studio-Aufgaben haben kein Projekt; ihr Kontakt steht an dessen Stelle.
        public ?string $contactId = null,
        public ?string $contactName = null,
    ) {}

    public static function fromTask(Task $task): self
    {
        return new self(
            id: $task->public_id,
            projectId: $task->case?->public_id,
            projectName: $task->case?->name,
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
            shootId: $task->shoot?->public_id,
            shootLabel: $task->shoot?->label ?? $task->shoot?->kind,
            audience: $task->audience->value,
            audienceLabel: $task->audience->label(),
            completedOn: $task->completed_at?->format('Y-m-d'),
            dueAnchorLabel: $task->dueAnchorLabel(),
            assigneeId: $task->assignee?->public_id,
            assigneeName: $task->assignee?->name,
            contactId: $task->contact?->public_id,
            contactName: $task->contact?->name,
        );
    }
}
