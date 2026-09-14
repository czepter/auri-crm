<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Crm\Support\TaskAnchors;
use App\Crm\Support\TaskDependencyGraph;
use App\Http\Controllers\Controller;
use App\Integration\Data\TaskReadData;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * #401: tasks on the api surface, mirroring App\Crm\Http\Controllers\
 * TaskController — the same fields, the same S-12 dependency guard, the same
 * blocked-completion refusal.
 */
class TaskController extends Controller
{
    public function __construct(private readonly TaskAnchors $anchors) {}

    /** `done` narrows to completed or open; omitted returns both, oldest due date first. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $filters = $request->validate(['done' => ['sometimes', 'in:true,false']]);

        $tasks = $project->tasks()
            ->with(['project:id,public_id', 'shoot', 'dependsOn', 'assignee'])
            ->when($request->has('done'), fn ($query) => $request->boolean('done')
                ? $query->whereNotNull('completed_at')
                : $query->open())
            ->byDueDate()
            ->get();

        return response()->json(['data' => $tasks->map(TaskReadData::fromModel(...))]);
    }

    public function store(Request $request, Project $project): JsonResponse
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

        $predecessor = isset($validated['depends_on'])
            ? $project->tasks()->where('public_id', $validated['depends_on'])->first()
            : null;

        abort_if(isset($validated['depends_on']) && $predecessor === null, 422);

        $anchor = $validated['anchor'] ?? null;
        $offset = $anchor === null ? null : (int) ($validated['offset_days'] ?? 0);

        abort_if(
            $anchor !== null
                && ! in_array($anchor, array_column($this->anchors->forProject($project), 'value'), true),
            422,
        );

        $task = $project->tasks()->create([
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
        $task->setRelation('project', $project);

        return response()->json(['data' => TaskReadData::fromModel($task)], 201);
    }

    public function depend(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate(['depends_on' => ['nullable', 'string']]);

        if (blank($validated['depends_on'] ?? null)) {
            $task->update(['depends_on_task_id' => null]);

            return response()->json(['data' => TaskReadData::fromModel($task->fresh(['project', 'shoot', 'dependsOn', 'assignee']))]);
        }

        $predecessor = Task::query()
            ->where('project_id', $task->project_id)
            ->where('public_id', $validated['depends_on'])
            ->first();

        abort_if($predecessor === null, 422);

        if (TaskDependencyGraph::wouldCycle($task, $predecessor)) {
            throw ValidationException::withMessages([
                'depends_on' => "\u{201e}{$predecessor->title}\u{201c} wartet selbst auf diese Aufgabe.",
            ]);
        }

        $task->update(['depends_on_task_id' => $predecessor->id]);

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['project', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'assignee' => ['nullable', 'string', Rule::exists('users', 'public_id')],
        ]);

        $task->update(['assignee_id' => $this->assigneeId($validated['assignee'] ?? null)]);

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['project', 'shoot', 'dependsOn', 'assignee']))]);
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $on = $request->validate([
            'completed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ])['completed_on'] ?? null;

        $done = $on !== null || ! $task->isDone();

        if ($done && $task->isBlocked()) {
            throw ValidationException::withMessages([
                'task' => "\u{201e}{$task->dependsOn->title}\u{201c} ist noch offen.",
            ]);
        }

        return $this->mark($request, $task, true, $on);
    }

    public function reopen(Request $request, Task $task): JsonResponse
    {
        return $this->mark($request, $task, false);
    }

    public function destroy(Task $task): Response
    {
        $task->delete();

        return response()->noContent();
    }

    private function mark(Request $request, Task $task, bool $done, ?string $on = null): JsonResponse
    {
        $actor = $this->actor($request);

        $task->forceFill([
            'completed_at' => $done ? ($on === null ? now() : CarbonImmutable::parse($on)->startOfDay()) : null,
            'completed_by_id' => $done ? $actor?->id : null,
        ])->save();

        AuditLogger::record(
            event: $done ? 'task.completed' : 'task.reopened',
            actor: $actor,
            context: ['title' => $task->title],
            subject: $task->public_id,
            projectId: $task->project_id,
        );

        return response()->json(['data' => TaskReadData::fromModel($task->fresh(['project', 'shoot', 'dependsOn', 'assignee']))]);
    }

    private function assigneeId(?string $publicId): ?int
    {
        return $publicId === null ? null : User::query()->where('public_id', $publicId)->value('id');
    }

    /** N-04: null for an ApiToken caller, the consenting staff member otherwise — see LeadController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
