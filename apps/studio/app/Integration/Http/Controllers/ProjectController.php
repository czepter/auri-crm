<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
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
     * Mirrors App\Crm\Http\Controllers\ProjectController::togglePortalVisibility,
     * but takes the value rather than flipping it: a retried toggle after a
     * dropped response would land on the wrong state, which `idempotent`
     * (routes/api.php) must never allow.
     */
    public function setPortalVisibility(Request $request, Project $project): JsonResponse
    {
        $visible = $request->validate(['visible' => ['required', 'boolean']])['visible'];

        $project->update(['portal_visible' => $visible]);

        $principal = $request->user('api');

        AuditLogger::record(
            event: $visible ? 'project.portal_shown' : 'project.portal_hidden',
            actor: $principal instanceof User ? $principal : null,
            subject: $project->public_id,
            projectId: $project->id,
        );

        return response()->json(['id' => $project->public_id, 'portalVisible' => $project->portal_visible]);
    }
}
