<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ]);

        $project->tasks()->create([
            'title' => $validated['title'],
            'audience' => $validated['audience'],
            'due_on' => $validated['due_on'] ?? null,
            'shoot_id' => isset($validated['shoot'])
                ? $project->shoots()->where('public_id', $validated['shoot'])->value('id')
                : null,
        ]);

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

        $task->forceFill([
            'completed_at' => $done ? now() : null,
            'completed_by_id' => $done ? auth('staff')->id() : null,
        ])->save();

        AuditLogger::record(
            event: $done ? 'task.completed' : 'task.reopened',
            actor: auth('staff')->user(),
            context: ['title' => $task->title],
            subject: $task->public_id,
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
