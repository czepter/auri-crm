<?php

namespace App\Portal\Http\Controllers;

use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
use App\Engagement\Data\ThreadData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E-06's own point: the client may start a conversation, not just read one.
 * Asks Contact::visibleProjects() the same question every other portal
 * controller does, so a project the contact is not on and one the studio has
 * not made portal_visible fail identically, as a 404.
 *
 * One thread per project reaches this screen — the project's latest — the
 * same simplification the admin Kommunikation tab makes; nothing here lists
 * more than one.
 */
class ThreadController extends Controller
{
    public function show(string $project): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)
            ->with(['latestThread.messages.contact', 'latestThread.messages.author'])
            ->firstOrFail();

        return Inertia::render('Threads/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'thread' => $found->latestThread === null ? null : ThreadData::fromModel($found->latestThread),
        ]);
    }

    /**
     * Starts the project's first thread, or replies on the one that already
     * exists — never both in the same request. StartThread and PostMessage
     * are the same two actions the admin side's own reply route and this
     * story's tests share.
     */
    public function store(Request $request, string $project, StartThread $start, PostMessage $post): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()->where('projects.public_id', $project)
            ->with('latestThread')
            ->firstOrFail();

        if ($found->latestThread === null) {
            $data = $request->validate([
                'subject' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:5000'],
            ]);

            $start($found, $contact, $data['subject'], $data['body']);
        } else {
            $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

            $post($found->latestThread, $contact, $data['body']);
        }

        return back();
    }
}
