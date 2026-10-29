<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\AssignTask;
use App\Crm\Actions\CompleteTask;
use App\Crm\Actions\CreateTask;
use App\Crm\Actions\DeleteTask;
use App\Crm\Actions\SetTaskDependency;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Integration\Data\TaskReadData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * #401: tasks on the api surface, mirroring App\Crm\Http\Controllers\
 * TaskController — the same fields, the same S-12 dependency guard, the same
 * blocked-completion refusal.
 */
class TaskController extends Controller
{
    /** `done` narrows to completed or open; omitted returns both, oldest due date first. */
    public function index(Request $request, ClientCase $case): JsonResponse
    {
        $filters = $request->validate(['done' => ['sometimes', 'in:true,false']]);

        $tasks = $case->tasks()
            ->with(['case:id,public_id', 'shoot', 'dependsOn', 'assignee'])
            ->when($request->has('done'), fn ($query) => $request->boolean('done')
                ? $query->whereNotNull('completed_at')
                : $query->open())
            ->byDueDate()
            ->get();

        return response()->json(['data' => $tasks->map(TaskReadData::fromModel(...))]);
    }

    public function store(Request $request, ClientCase $case, CreateTask $createTask): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            'due_on' => ['nullable', 'date', 'prohibits:anchor'],
            'anchor' => ['nullable', 'string'],
            'offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'shoot' => ['nullable', 'string', Rule::exists('shoots', 'public_id')],
            'depends_on' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $task = $createTask->forCase($case, $validated);
        $task->setRelation('case', $case);

        return response()->json(['data' => TaskReadData::fromModel($task)], 201);
    }

    public function depend(Request $request, Task $task, SetTaskDependency $setDependency): JsonResponse
    {
        $validated = $request->validate(['depends_on' => ['nullable', 'string']]);

        $setDependency($task, $validated['depends_on'] ?? null);

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['case', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function assign(Request $request, Task $task, AssignTask $assignTask): JsonResponse
    {
        $validated = $request->validate([
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $assignTask($task, $validated['assignee'] ?? null);

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['case', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function complete(Request $request, Task $task, CompleteTask $completeTask): JsonResponse
    {
        $on = $request->validate([
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ])['completed_on'] ?? null;

        $completeTask($task, $this->actor($request), $on);

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['case', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function reopen(Request $request, Task $task, CompleteTask $completeTask): JsonResponse
    {
        $completeTask->reopen($task, $this->actor($request));

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['case', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function destroy(Task $task, DeleteTask $deleteTask): Response
    {
        $deleteTask($task);

        return response()->noContent();
    }

    /** N-04: null for an ApiToken caller, the consenting staff member otherwise — see LeadController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
