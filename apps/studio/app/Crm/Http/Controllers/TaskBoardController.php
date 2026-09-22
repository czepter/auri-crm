<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\TaskRowData;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-24: every task the studio owes, across projects.
 *
 * The project's own Aufgaben tab answers "what is left on this job"; this
 * answers "what is left on me", which is the question an assignment creates
 * and which no per-project list can be asked.
 */
class TaskBoardController extends Controller
{
    public function __invoke(): Response
    {
        $viewer = auth('staff')->user();

        return Inertia::render('Tasks/Index', [
            'tasks' => TaskRowData::collect(
                Task::query()
                    ->visibleTo($viewer)
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->with(['project', 'contact', 'shoot', 'assignee', 'dependsOn'])
                    ->get()
                    ->map(TaskRowData::fromTask(...))
                    ->values()
            ),
            'staffOptions' => User::query()->orderBy('name')->get()
                ->map(fn (User $user) => ['value' => $user->public_id, 'label' => $user->name])
                ->all(),
        ]);
    }
}
