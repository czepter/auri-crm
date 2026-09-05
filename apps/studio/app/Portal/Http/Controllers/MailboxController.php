<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Actions\StartThread;
use App\Engagement\Data\ThreadData;
use App\Engagement\Models\Thread;
use App\Http\Controllers\Controller;
use App\Portal\Support\PortalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * P-04 (#223). One mailbox, not one per booking.
 *
 * A client writes to the studio, not to an order. Which booking a
 * conversation is about is something the thread carries — visibly, as its
 * own line — and something it may not carry at all, because the question
 * that comes before the booking ("do you still have anything free in
 * autumn?") is the one a per-project inbox makes impossible to ask.
 *
 * Every read is scoped through Contact::threads() (the thread_participants
 * join), never through the project. A thread with no project is reachable
 * exactly the same way as one with; a thread the contact is not a
 * participant on is a 404 either way.
 *
 * No read state: nothing in the schema records what a client has opened. The
 * rail's dot answers a question the data can answer instead — is the newest
 * line the studio's — which is what the header badge has always counted.
 */
class MailboxController extends Controller
{
    public function index(Request $request, PortalSettings $settings): Response
    {
        $contact = auth('portal')->user();

        $threads = $contact->threads()
            ->with(['project', 'latestMessage'])
            ->get()
            ->sortByDesc(fn (Thread $thread) => $thread->latestMessage?->created_at ?? $thread->created_at)
            ->values();

        // ?unterhaltung= names the thread to open; without one the newest is open,
        // which is where a client arriving from a notification mail expects
        // to land. ?betrifft= only preselects the compose field — it is the
        // link a booking screen points at, not a filter.
        $open = $request->query('unterhaltung') === null
            ? $threads->first()
            : $threads->firstWhere('public_id', $request->query('unterhaltung'));

        $open?->load(['messages.contact', 'messages.author']);

        return Inertia::render('Threads/Index', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'threads' => $threads->map(fn (Thread $thread) => [
                'id' => $thread->public_id,
                'subject' => $thread->subject,
                'reference' => $thread->project?->name,
                'snippet' => $thread->latestMessage?->body,
                'lastAt' => ($thread->latestMessage?->created_at ?? $thread->created_at)->toIso8601String(),
                // The same question HandleInertiaRequests::portalNav() counts
                // for the header badge, per row: whoever wrote the newest line
                // owes the next one.
                'waiting' => $thread->latestMessage !== null
                    && $thread->latestMessage->contact_id === null,
            ])->all(),
            'openThread' => $open === null ? null : ThreadData::fromModel($open),
            'openReference' => $open?->project?->name,
            'bookings' => $this->bookings($contact),
            'preselected' => $request->query('betrifft'),
            // P-09 (#228): messaging is three states. `booking` means every
            // thread must name one, so the "Allgemein" choice is not offered
            // — and store() refuses it, not just the form.
            'generalAllowed' => $settings->generalThreadsAllowed($contact),
        ]);
    }

    /**
     * A new conversation. `project` is optional and, when given, has to be
     * one of this contact's own visible bookings — the same
     * visibleProjects() question every other portal controller asks, so a
     * booking they are not on fails here as it does everywhere else.
     */
    public function store(Request $request, StartThread $start, PortalSettings $settings): RedirectResponse
    {
        $contact = auth('portal')->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'project' => [$settings->generalThreadsAllowed($contact) ? 'nullable' : 'required', 'string'],
        ]);

        $project = ($data['project'] ?? '') === ''
            ? null
            : $contact->visibleProjects()->where('projects.public_id', $data['project'])->firstOrFail();

        $thread = $start($project, $contact, $data['subject'], $data['body']);

        return redirect("/nachrichten?unterhaltung={$thread->public_id}");
    }

    public function reply(Request $request, string $thread, PostMessage $post): RedirectResponse
    {
        $contact = auth('portal')->user();

        $found = $contact->threads()->where('threads.public_id', $thread)->firstOrFail();

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $post($found, $contact, $data['body']);

        return back();
    }

    /** The "Betrifft" choices: this contact's visible bookings, newest first. */
    private function bookings($contact): array
    {
        return $contact->visibleProjects()
            ->with('primaryShoot')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->public_id,
                'label' => $project->primaryShoot?->date === null
                    ? $project->name
                    : $project->name.' · '.$project->primaryShoot->date->format('d.m.Y'),
            ])
            ->all();
    }
}
