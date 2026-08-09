<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's own page. Mockup 06 fills it with a questionnaire, a contract
 * and a gallery; none of those exist in P1b, so the page shows the shoots the
 * studio has chosen to share and says nothing it cannot back.
 *
 * portal_visible is the switch, and it defaults to false — a raw inquiry never
 * appears here the moment it arrives (vision §3.1).
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $contact = auth('portal')->user();

        $projects = $contact->visibleProjects()
            ->with(['shoots' => fn ($query) => $query->uncancelled()->orderByRaw('date is null, date')])
            ->get();

        return Inertia::render('Home', [
            'contact' => ['name' => $contact->name],
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->public_id,
                'name' => $project->name,
                'type' => $project->type->label(),
                'shoots' => $project->shoots->map(fn ($shoot) => [
                    'id' => $shoot->public_id,
                    'title' => $shoot->label ?? $shoot->kind->label(),
                    'date' => $shoot->date?->format('Y-m-d'),
                    'callTime' => $shoot->call_time === null
                        ? null
                        : substr((string) $shoot->call_time, 0, 5),
                    'venue' => $shoot->venue(),
                    // The client is told where their shoot stands, in the same
                    // words the studio uses. The temperature differs, not the
                    // fact: nothing here is coloured as a warning, because the
                    // studio chases and the client is not chased in their own
                    // portal.
                    'status' => $shoot->status->label(),
                ]),
            ]),
        ]);
    }
}
