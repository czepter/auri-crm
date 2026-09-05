<?php

namespace App\Engagement\Http\Controllers;

use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Models\Message;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $validated = $this->validated($request);

        $post(
            $thread,
            auth('staff')->user(),
            $validated['body'],
            $validated['scheduled_for'] === null ? null : Carbon::parse($validated['scheduled_for']),
        );

        return back();
    }

    /**
     * E-13: cancel a message that has not gone out yet.
     *
     * Deleted rather than flagged. It was never sent, nobody has seen it, and
     * a "cancelled" tombstone in a client conversation is a row the studio
     * would have to read past forever. The queued job notices the row is gone
     * and does nothing — see SendScheduledMessage.
     */
    public function cancel(Project $project, Message $message): RedirectResponse
    {
        $this->authorize('manageMessages', $project);

        abort_if($message->scheduled_for === null || $message->delivery_state !== null, 422);
        abort_if($message->thread->project_id !== $project->id, 404);

        $message->delete();

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
    /** @return array{body: string, scheduled_for: ?string} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\{\{.*\}\}/s'],
            // E-13. `after:now` rather than `after_or_equal`: scheduling a
            // send for a moment already past is a mistake, not an
            // instruction, and the immediate path is one click away.
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ], [
            'body.not_regex' => 'Die Nachricht enthält ein nicht aufgelöstes Feld.',
        ]);

        return [
            'body' => $validated['body'],
            'scheduled_for' => $validated['scheduled_for'] ?? null,
        ];
    }
}
