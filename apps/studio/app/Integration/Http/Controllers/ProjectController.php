<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\BookProject;
use App\Crm\Actions\CompleteProject;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Integration\Data\JobReadData;
use App\Models\User;
use App\Support\LiveUpdate;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Taking an import back. A run that captured the wrong rows has no other way
 * out: every write on this surface so far only ever adds.
 *
 * There is no delete_lead beside this. A lead is born with its project and
 * means nothing without it, so the project is the unit that goes.
 *
 * What the project owns alone — the lead, the shoots, the notes, the contact
 * links, the tasks and the conversation — the database removes with it (every
 * project_id foreign key is cascadeOnDelete). The contacts stay: they are
 * people, reachable from other projects.
 */
class ProjectController extends Controller
{
    public function destroy(Project $project): Response
    {
        $blocking = $project->deletionBlockers();

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'project' => 'The project has a '.$blocking->join(', ', ' and ').' and cannot be deleted.',
            ]);
        }

        $project->delete();

        LiveUpdate::publish();

        return response()->noContent();
    }

    /**
     * #401: book/complete/reopen on the api surface, mirroring
     * App\Crm\Http\Controllers\ProjectController — the same BookProject and
     * CompleteProject actions the admin buttons call, and the same
     * already-booked no-op BookProject itself guards.
     */
    public function book(Request $request, Project $project, BookProject $book): JsonResponse
    {
        $job = $book($project, $this->actor($request));

        return response()->json(['data' => JobReadData::fromModel(($job ?? $project->job)->setRelation('project', $project))]);
    }

    public function complete(Request $request, Project $project, CompleteProject $complete): JsonResponse
    {
        try {
            $complete($project, $this->actor($request));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $project->public_id, 'completedAt' => $project->completed_at?->toIso8601String()]]);
    }

    public function reopen(Request $request, Project $project, CompleteProject $complete): JsonResponse
    {
        try {
            $complete->reopen($project, $this->actor($request));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $project->public_id, 'completedAt' => $project->completed_at?->toIso8601String()]]);
    }

    /** N-04: null for an ApiToken caller, the consenting staff member otherwise — see LeadController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
