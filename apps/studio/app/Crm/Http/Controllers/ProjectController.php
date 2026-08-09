<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\BookProject;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\InternalNoteData;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\CrewRole;
use App\Crm\Models\Project;
use App\Data\StaffProfileData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly DeriveProjectPosition $position) {}

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'header' => $this->header($project),
            'shoots' => ShootData::collect($this->shootsOf($project)),
            'notes' => InternalNoteData::collect(
                $project->notes()->with('author')->get()->map(InternalNoteData::fromNote(...))
            ),
            // Open first and by due date, then the done ones: a card whose top
            // row is something already finished buries the work.
            'tasks' => TaskRowData::collect(
                $project->tasks()
                    ->with(['project', 'shoot'])
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->get()
                    ->map(TaskRowData::fromTask(...))
            ),
            'shootOptions' => $project->shoots()->uncancelled()->get()
                ->map(fn ($shoot) => [
                    'value' => $shoot->public_id,
                    'label' => $shoot->label ?? $shoot->kind->label(),
                ])
                ->all(),
        ]);
    }

    public function shoots(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Shoots', [
            'header' => $this->header($project),
            'shoots' => ShootData::collect($this->shootsOf($project)),
            // Who can be put on a shooting day, and in what capacity. Both
            // lists are the studio's, not one shoot's.
            'staff' => StaffProfileData::collect(User::query()->orderBy('name')->get()),
            'crewRoles' => collect(CrewRole::cases())
                ->map(fn (CrewRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    public function book(Project $project, BookProject $book): RedirectResponse
    {
        $this->authorize('book', $project);

        // Already booked is a no-op inside the action rather than a failure
        // here: two staff members pressing the button is not an error, it is a
        // race, and the second press should leave the screen consistent.
        $book($project, auth('staff')->user());

        return back();
    }

    private function header(Project $project): ProjectHeaderData
    {
        return ProjectHeaderData::fromProject(
            $project->load(['contacts', 'shoots', 'primaryShoot', 'job']),
            ($this->position)($project),
        );
    }

    /**
     * Chronological, undated last. sort_order breaks the tie for two shoots on
     * one day — the ceremony before the reception, whatever the studio decided.
     */
    private function shootsOf(Project $project)
    {
        return $project->shoots()
            ->with('crew.user')
            ->reorder()
            ->orderByRaw('date is null, date')
            ->orderBy('sort_order')
            ->get()
            ->map(ShootData::fromShoot(...));
    }
}
