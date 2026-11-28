<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AssignTask;
use App\Crm\Actions\CompleteTask;
use App\Crm\Actions\CreateTask;
use App\Crm\Actions\DeleteTask;
use App\Crm\Actions\SetTaskDependency;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Task;
use App\Engagement\Models\Thread;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request, ClientCase $case, CreateTask $createTask): RedirectResponse
    {
        $this->authorize('manageTasks', $case);

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
            'thread' => ['nullable', 'string', Rule::exists('threads', 'public_id')],
        ]);

        // A thread of this project only: a link may not reach across projects.
        $validated['thread_id'] = isset($validated['thread'])
            ? $case->threads()->where('public_id', $validated['thread'])->value('id')
            : null;

        $createTask->forCase($case, $validated);

        return back();
    }

    /** Studio work with no project behind it. Always internal: a client task needs a project to be about. */
    public function storeStudio(Request $request, CreateTask $createTask): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['prohibited'],
            'due_on' => ['nullable', 'date'],
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
            'contact' => ['nullable', 'string', Rule::exists('contacts', 'public_id')],
            'thread' => ['nullable', 'string', Rule::exists('threads', 'public_id')],
        ]);

        // Studio work links only a conversation that belongs to no project yet.
        $validated['thread_id'] = isset($validated['thread'])
            ? Thread::query()->whereNull('case_id')->where('public_id', $validated['thread'])->value('id')
            : null;

        $createTask->forStudio($validated);

        return back();
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
        ]);

        $task->title = $validated['title'];

        // A resolved anchor date is sent back unchanged; only a different day
        // replaces the anchor, or saving the title would pin every anchored task.
        if (($validated['due_on'] ?? null) !== $task->due_on?->format('Y-m-d')) {
            $task->fill(['due_on' => $validated['due_on'] ?? null, 'due_anchor' => null, 'due_offset_days' => null]);
        }

        $task->save();

        return back();
    }

    /**
     * S-12: change what a task waits on. A cycle is refused here, at write
     * time — see TaskDependencyGraph for why discovering one at read time is
     * worse than refusing the save.
     */
    public function depend(Request $request, Task $task, SetTaskDependency $setDependency): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(['depends_on' => ['nullable', 'string']]);

        $setDependency($task, $validated['depends_on'] ?? null);

        return back();
    }

    /** T-24: hand a task over, or take it back. Its own route for the reason depend() has one. */
    public function assign(Request $request, Task $task, AssignTask $assignTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $assignTask($task, $validated['assignee'] ?? null);

        return back();
    }

    /**
     * Completion is a toggle rather than a delete: what was owed and when it
     * was done is the record, and a removed row answers neither question.
     */
    public function complete(Request $request, Task $task, CompleteTask $completeTask): RedirectResponse
    {
        $this->authorize('update', $task);

        // A day, when the work was actually done rather than ticked off. It
        // makes the call a statement instead of a toggle: sending one on a
        // task already done corrects its date rather than reopening it.
        $on = $request->validate([
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ])['completed_on'] ?? null;

        $actor = auth('staff')->user();

        if ($on === null && $task->isDone()) {
            $completeTask->reopen($task, $actor);
        } else {
            $completeTask($task, $actor, $on);
        }

        return back();
    }

    /**
     * The counterpart to complete(), for the list that only shows done ones:
     * there the toggle has no checkbox to click, and a second click on
     * complete() would tick a task that is already ticked.
     */
    public function reopen(Task $task, CompleteTask $completeTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $completeTask->reopen($task, auth('staff')->user());

        return back();
    }

    public function destroy(Task $task, DeleteTask $deleteTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $deleteTask($task);

        return back();
    }
}
