<?php

namespace App\Crm\Actions;

use App\Crm\Data\ThreadEntryData;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Crm\Support\ProjectActivity;

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
    public function __invoke(Project $project): array
    {
        $project->loadMissing(['notes.author', 'tasks']);

        $notes = $project->notes->map(fn (InternalNote $note) => new ThreadEntryData(
            key: "note-{$note->public_id}",
            at: $note->created_at->toIso8601String(),
            body: $note->body,
            // I-03: a note written through the api surface has no staff author.
            author: $note->author?->name ?? 'API',
            system: false,
        ));

        $events = collect(ProjectActivity::for($project))
            ->map(fn (array $event, int $index) => new ThreadEntryData(
                key: "event-{$index}",
                at: $event['at'],
                body: $event['label'],
                author: $event['actorName'],
                system: true,
            ));

        $done = $project->tasks->filter->isDone()->map(fn (Task $task) => new ThreadEntryData(
            key: "task-{$task->public_id}",
            at: $task->due_on?->toDateString(),
            body: $task->title,
            author: null,
            system: true,
        ));

        // Oldest first, so the composer sits where the next line goes. A task
        // carries a date and nothing else; it sorts to the head of its day,
        // ahead of the events that day, which is the order the day happened in.
        return $notes->concat($events)->concat($done)
            ->sortBy(fn (ThreadEntryData $entry) => $entry->at ?? '9999')
            ->values()
            ->all();
    }
}
