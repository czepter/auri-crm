<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Crm\Support\TaskDependencyGraph;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            'due_on' => ['nullable', 'date'],
            'shoot' => ['nullable', 'string', Rule::exists('shoots', 'public_id')],
            // S-12: what this one is waiting on. Scoped to the project below
            // rather than by an exists rule alone — a task may only depend on
            // one of its own project's, or a dependency would reach across
            // two studios' worth of work.
            'depends_on' => ['nullable', 'string'],
        ]);

        $predecessor = isset($validated['depends_on'])
            ? $project->tasks()->where('public_id', $validated['depends_on'])->first()
            : null;

        abort_if(isset($validated['depends_on']) && $predecessor === null, 422);

        $project->tasks()->create([
            'title' => $validated['title'],
            'audience' => $validated['audience'],
            'due_on' => $validated['due_on'] ?? null,
            'shoot_id' => isset($validated['shoot'])
                ? $project->shoots()->where('public_id', $validated['shoot'])->value('id')
                : null,
            'depends_on_task_id' => $predecessor?->id,
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

    /**
     * Completion is a toggle rather than a delete: what was owed and when it
     * was done is the record, and a removed row answers neither question.
     */
    public function complete(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $done = ! $task->isDone();

        // S-12: a blocked task cannot be completed. Reopening one always can
        // — undoing a mistake must not itself be blocked by the thing the
        // mistake was about.
        if ($done && $task->isBlocked()) {
            throw ValidationException::withMessages([
                'task' => "„{$task->dependsOn->title}“ ist noch offen.",
            ]);
        }

        $task->forceFill([
            'completed_at' => $done ? now() : null,
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
