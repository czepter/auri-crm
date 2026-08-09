<?php

namespace App\Engagement\Http\Controllers;

use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff's half of E-06: a reply on a thread the client already opened. There
 * is no store action here that starts one — see StartThread's own doc
 * comment for why that stays the client's alone.
 */
class ThreadController extends Controller
{
    public function reply(Request $request, Project $project, PostMessage $post): RedirectResponse
    {
        $this->authorize('manageMessages', $project);

        $thread = $project->latestThread()->first();

        // Nothing to reply to yet — the client has not started a
        // conversation, and staff cannot start one on their behalf.
        abort_if($thread === null, 404);

        $post($thread, auth('staff')->user(), $this->validated($request));

        return back();
    }

    private function validated(Request $request): string
    {
        return $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
    }
}
