<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\InternalNote;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Studio-only, and only ever registered in routes/admin.php. Nothing here may
 * ever be lifted into a portal surface — InternalNoteTest holds that line.
 *
 * No ownership rule on update or delete: the audience is the whole studio, and
 * audit_logs already records who changed what.
 */
class InternalNoteController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageNotes', $project);

        $project->notes()->create([
            'author_id' => auth('staff')->id(),
            'body' => $this->validated($request),
        ]);

        return back();
    }

    public function update(Request $request, InternalNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->update(['body' => $this->validated($request)]);

        return back();
    }

    public function destroy(InternalNote $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->delete();

        return back();
    }

    private function validated(Request $request): string
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ])['body'];
    }
}
