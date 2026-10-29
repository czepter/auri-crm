<?php

namespace App\Crm\Actions;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Task;
use App\Crm\Support\TaskAnchors;
use App\Models\User;

/** The only writer of new tasks. */
class CreateTask
{
    public function __construct(private readonly TaskAnchors $anchors) {}

    /** @param array<string, mixed> $attributes */
    public function __invoke(array $attributes): Task
    {
        return Task::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $validated  title, audience and optionally
     *                                           due_on, anchor, offset_days, shoot, depends_on, assignee
     */
    public function forCase(ClientCase $case, array $validated): Task
    {
        $predecessor = isset($validated['depends_on'])
            ? $case->tasks()->where('public_id', $validated['depends_on'])->first()
            : null;

        abort_if(isset($validated['depends_on']) && $predecessor === null, 422);

        $anchor = $validated['anchor'] ?? null;
        $offset = $anchor === null ? null : (int) ($validated['offset_days'] ?? 0);

        // Only what this project can still reach. An anchor from a cancelled
        // shoot or a stage the funnel already skipped would never resolve to
        // a day, and would sit on the list as a deadline that never arrives.
        abort_if(
            $anchor !== null
                && ! in_array($anchor, array_column($this->anchors->forCase($case), 'value'), true),
            422,
        );

        return $this([
            'case_id' => $case->id,
            'title' => $validated['title'],
            'audience' => $validated['audience'],
            'due_on' => $anchor === null
                ? $validated['due_on'] ?? null
                : $this->anchors->dueOn($case, $anchor, $offset)?->format('Y-m-d'),
            'due_anchor' => $anchor,
            'due_offset_days' => $offset,
            'shoot_id' => isset($validated['shoot'])
                ? $case->shoots()->where('public_id', $validated['shoot'])->value('id')
                : null,
            'depends_on_task_id' => $predecessor?->id,
            'assignee_id' => $this->assigneeId($validated['assignee'] ?? null),
        ]);
    }

    /**
     * Studio work with no project behind it. Always internal: a client task
     * needs a project to be about.
     *
     * @param  array<string, mixed>  $validated
     */
    public function forStudio(array $validated): Task
    {
        return $this([
            'title' => $validated['title'],
            'audience' => TaskAudience::Internal,
            'due_on' => $validated['due_on'] ?? null,
            'assignee_id' => $this->assigneeId($validated['assignee'] ?? null),
            'contact_id' => isset($validated['contact'])
                ? Contact::query()->where('public_id', $validated['contact'])->value('id')
                : null,
        ]);
    }

    private function assigneeId(?string $publicId): ?int
    {
        return $publicId === null ? null : User::query()->where('public_id', $publicId)->value('id');
    }
}
