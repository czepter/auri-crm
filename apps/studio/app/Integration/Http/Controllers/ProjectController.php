<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\LiveUpdate;
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
}
