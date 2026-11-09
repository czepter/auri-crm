<?php

namespace App\Crm\Actions;

use App\Crm\Data\ThreadEntryData;
use App\Crm\Models\ClientCase;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Task;
use App\Crm\Support\CaseActivity;
use Carbon\CarbonImmutable;

/**
 * C-11 (#247): notes and the audit trail are one trace, so they are also one
 * order. Sorted apart they read as two lists that happen to share a heading.
 *
 * Built here rather than in the screen: the order is what makes the trace a
 * trace, and a rule the client assembles is one no test can reach.
 */
class BuildThread
{
    /** @return list<ThreadEntryData> */
    public function __invoke(ClientCase $project): array
    {
        $project->loadMissing(['notes.author', 'tasks']);

        $notes = $project->notes->map(fn (InternalNote $note) => new ThreadEntryData(
            key: "note-{$note->public_id}",
            at: $note->happenedAt()->toIso8601String(),
            body: $note->body,
            // I-03: a note written through the api surface has no staff author.
            author: $note->author?->name ?? 'API',
            system: false,
            note: $note->public_id,
        ));

        $events = collect(CaseActivity::for($project))
            ->map(fn (array $event, int $index) => new ThreadEntryData(
                key: "event-{$index}",
                at: $event['at'],
                body: $event['label'],
                author: $event['actorName'],
                system: true,
            ));

        $done = $project->tasks->filter->isDone()->map(fn (Task $task) => new ThreadEntryData(
            key: "task-{$task->public_id}",
            at: ($task->completed_at ?? $task->due_on)?->toIso8601String(),
            body: $task->title,
            author: null,
            system: true,
        ));

        // Oldest first, so the composer sits where the next line goes. By
        // instant, not by string: offsets differ between a stored occurred_at
        // and a created_at, and strings sort them out of order.
        return $notes->concat($events)->concat($done)
            ->sortBy(fn (ThreadEntryData $entry) => $entry->at === null ? PHP_INT_MAX : CarbonImmutable::parse($entry->at)->getTimestamp())
            ->values()
            ->all();
    }
}
