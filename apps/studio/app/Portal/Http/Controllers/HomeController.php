<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The switcher. Contact::visibleProjects() — projects joined through
 * project_contacts for the authenticated contact, where portal_visible — is
 * the one place this question is asked, and ProjectController asks it the
 * same way for direct entry. Nothing here is written to the session: the
 * list is built fresh on every request, so revoking access takes effect on
 * the next click rather than the next login.
 *
 * A single-project contact has nothing to switch between, so they never see
 * a picker with one option in it — a redirect sends them straight to their
 * project. Zero projects is an empty state, not an error.
 */
class HomeController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $contact = auth('portal')->user();

        $projects = $contact->visibleProjects()->get();

        if ($projects->count() === 1) {
            return redirect("/projekte/{$projects->first()->public_id}");
        }

        return Inertia::render('Home', [
            'contact' => ['name' => $contact->name],
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->public_id,
                'name' => $project->name,
                'type' => $project->type->label(),
            ])->values(),
        ]);
    }
}
