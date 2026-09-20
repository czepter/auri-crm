<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Crm\Support\TaskAnchors;
use App\Crm\Support\TaskDependencyGraph;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(private readonly TaskAnchors $anchors) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            // A fixed day or an anchor, never both — two answers to "when" is
            // one of them going stale.
            'due_on' => ['nullable', 'date', 'prohibits:anchor'],
            'anchor' => ['nullable', 'string'],
            'offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'shoot' => ['nullable', 'string', Rule::exists('shoots', 'public_id')],
            // S-12: what this one is waiting on. Scoped to the project below
            // rather than by an exists rule alone — a task may only depend on
            // one of its own project's, or a dependency would reach across
            // two studios' worth of work.
            'depends_on' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $predecessor = isset($validated['depends_on'])
            ? $project->tasks()->where('public_id', $validated['depends_on'])->first()
            : null;

        abort_if(isset($validated['depends_on']) && $predecessor === null, 422);

        $anchor = $validated['anchor'] ?? null;
        $offset = $anchor === null ? null : (int) ($validated['offset_days'] ?? 0);

        // Only what this project can still reach. An anchor from a cancelled
        // shoot or a stage the funnel already skipped would never resolve to
        // a day, and would sit on the list as a deadline that never arrives.
        abort_if(
            $anchor !== null
                && ! in_array($anchor, array_column($this->anchors->forProject($project), 'value'), true),
            422,
        );

        $project->tasks()->create([
            'title' => $validated['title'],
            'audience' => $validated['audience'],
            'due_on' => $anchor === null
                ? $validated['due_on'] ?? null
                : $this->anchors->dueOn($project, $anchor, $offset)?->format('Y-m-d'),
            'due_anchor' => $anchor,
            'due_offset_days' => $offset,
            'shoot_id' => isset($validated['shoot'])
                ? $project->shoots()->where('public_id', $validated['shoot'])->value('id')
                : null,
            'depends_on_task_id' => $predecessor?->id,
            'assignee_id' => $this->assigneeId($validated['assignee'] ?? null),
        ]);

        return back();
    }

    /** Studio work with no project behind it. Always internal: a client task needs a project to be about. */
    public function storeStudio(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['prohibited'],
            'due_on' => ['nullable', 'date'],
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
            'contact' => ['nullable', 'string', Rule::exists('contacts', 'public_id')],
        ]);

        Task::create([
            'title' => $validated['title'],
            'audience' => TaskAudience::Internal,
            'due_on' => $validated['due_on'] ?? null,
            'assignee_id' => $this->assigneeId($validated['assignee'] ?? null),
            'contact_id' => isset($validated['contact'])
                ? Contact::query()->where('public_id', $validated['contact'])->value('id')
                : null,
        ]);

        return back();
    }

    /**
     * S-12: change what a task waits on. A cycle is refused here, at write
     * time — see TaskDependencyGraph for why discovering one at read time is
     * worse than refusing the save.
     */
    public function depend(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(['depends_on' => ['nullable', 'string']]);

        if (blank($validated['depends_on'] ?? null)) {
            $task->update(['depends_on_task_id' => null]);

            return back();
        }

        $predecessor = Task::query()
            ->where('project_id', $task->project_id)
            ->where('public_id', $validated['depends_on'])
            ->first();

        abort_if($predecessor === null, 422);

        if (TaskDependencyGraph::wouldCycle($task, $predecessor)) {
            throw ValidationException::withMessages([
                'depends_on' => "„{$predecessor->title}“ wartet selbst auf diese Aufgabe.",
            ]);
        }

        $task->update(['depends_on_task_id' => $predecessor->id]);

        return back();
    }

    /** T-24: hand a task over, or take it back. Its own route for the reason depend() has one. */
    public function assign(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $task->update(['assignee_id' => $this->assigneeId($validated['assignee'] ?? null)]);

        return back();
    }

    private function assigneeId(?string $publicId): ?int
    {
        return $publicId === null ? null : User::query()->where('public_id', $publicId)->value('id');
    }

    /**
     * Completion is a toggle rather than a delete: what was owed and when it
     * was done is the record, and a removed row answers neither question.
     */
    public function complete(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        // A day, when the work was actually done rather than ticked off. It
        // makes the call a statement instead of a toggle: sending one on a
        // task already done corrects its date rather than reopening it.
        $on = $request->validate([
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ])['completed_on'] ?? null;

        $done = $on !== null || ! $task->isDone();

        // S-12: a blocked task cannot be completed. Reopening one always can
        // — undoing a mistake must not itself be blocked by the thing the
        // mistake was about.
        if ($done && $task->isBlocked()) {
            throw ValidationException::withMessages([
                'task' => "„{$task->dependsOn->title}“ ist noch offen.",
            ]);
        }

        return $this->mark($task, $done, $on);
    }

    /**
     * The counterpart to complete(), for the list that only shows done ones:
     * there the toggle has no checkbox to click, and a second click on
     * complete() would tick a task that is already ticked.
     */
    public function reopen(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        return $this->mark($task, false);
    }

    private function mark(Task $task, bool $done, ?string $on = null): RedirectResponse
    {
        $task->forceFill([
            'completed_at' => $done ? ($on === null ? now() : CarbonImmutable::parse($on)->startOfDay()) : null,
            'completed_by_id' => $done ? auth('staff')->id() : null,
        ])->save();

        AuditLogger::record(
            event: $done ? 'task.completed' : 'task.reopened',
            actor: auth('staff')->user(),
            context: ['title' => $task->title],
            subject: $task->public_id,
            projectId: $task->project_id,
        );

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->delete();

        return back();
    }
}
