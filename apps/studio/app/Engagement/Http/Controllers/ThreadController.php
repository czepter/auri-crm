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

    /**
     * E-10: nothing leaves with a merge field still in it. Templates resolve
     * when they are inserted (MailTemplateData), so a `{{ … }}` surviving to
     * this point is either a typed one or a pasted one — and the client
     * would receive it verbatim, since messages have no second resolution
     * pass. Refused rather than silently blanked, so the staff member finds
     * out while they can still fix it.
     */
    private function validated(Request $request): string
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\{\{.*\}\}/s'],
        ], [
            'body.not_regex' => 'Die Nachricht enthält ein nicht aufgelöstes Feld.',
        ])['body'];
    }
}
