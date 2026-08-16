<?php

namespace App\Engagement\Http\Controllers;

use App\Engagement\Actions\PostMessage;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Engagement\Support\InboundAddress;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Settings\Actions\RotateInboundSecret;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E-12's one screen: everything that arrived and did not thread itself, plus
 * the two addresses the studio hands out.
 *
 * The triage tray is the point. A mail that reached us and matched nothing is
 * the case this whole design exists to make visible — the alternative is an
 * ingest that silently drops what it cannot place, and "the client says they
 * replied and we never saw it" with nowhere to look.
 *
 * The catch-all address is shown; the secret behind it never is. Rotation
 * lives here as a control, but the authority check is in
 * RotateInboundSecret itself — a gate on a screen is not a gate on an
 * operation.
 */
class InboxController extends Controller
{
    public function index(StudioSettingsResolver $settings): Response
    {
        $this->authorizeInbox();

        $secret = $settings->inboundSecret();
        $user = auth('staff')->user();

        return Inertia::render('Posteingang', [
            'configured' => InboundAddress::configured(),
            // The studio's own name in the address, not a generic handle:
            // this is the one inbound address a studio prints on a website
            // or reads out over the phone.
            'catchAll' => $secret === null
                ? null
                : InboundAddress::catchAll($settings->displayName() ?? '', $secret),
            'canRotate' => $user?->role->allows(Capability::ManageSettings) ?? false,
            'pending' => InboundMessage::query()
                ->whereIn('state', [InboundMessageState::Triage, InboundMessageState::Quarantined])
                ->with('thread.project')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (InboundMessage $row) => [
                    'id' => $row->public_id,
                    'from' => $row->from_address,
                    'to' => $row->to_address,
                    'subject' => $row->subject,
                    'body' => $row->body,
                    'state' => $row->state->value,
                    'stateLabel' => $row->state->label(),
                    'reason' => $row->reason,
                    'receivedAt' => $row->created_at->toIso8601String(),
                    'projectName' => $row->thread?->project?->name,
                ])
                ->all(),
            // Only threads a staff member could plausibly attach to: the ones
            // with a client on them. A thread with no contact participant has
            // nobody the mail could have come from.
            'threads' => Thread::query()
                ->whereHas('participants', fn ($query) => $query->whereNotNull('contact_id'))
                ->with('project')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Thread $thread) => [
                    'id' => $thread->public_id,
                    'label' => ($thread->project?->name ?? '—').' · '.$thread->subject,
                ])
                ->all(),
        ]);
    }

    /**
     * The deliberate attach the ingest refused to make on its own. A human
     * decides which conversation this belongs on, which is the whole reason
     * the automatic path insists on all three of its conditions.
     */
    public function attach(Request $request, InboundMessage $inboundMessage, PostMessage $post): RedirectResponse
    {
        $this->authorizeInbox();

        $validated = $request->validate(['thread' => ['required', 'string']]);

        $thread = Thread::query()->where('public_id', $validated['thread'])->firstOrFail();

        $contact = $thread->participants()
            ->whereNotNull('contact_id')
            ->with('contact')
            ->get()
            ->pluck('contact')
            ->first(fn ($contact) => mb_strtolower((string) $contact?->email) === $inboundMessage->from_address)
            ?? $thread->participants()->whereNotNull('contact_id')->with('contact')->first()?->contact;

        abort_if($contact === null, 422);

        $message = $post($thread, $contact, $inboundMessage->body);

        $inboundMessage->update([
            'state' => InboundMessageState::Threaded,
            'reason' => null,
            'thread_id' => $thread->id,
            'contact_id' => $contact->id,
            'message_id_ref' => $message->id,
        ]);

        return back();
    }

    public function rotate(RotateInboundSecret $rotate): RedirectResponse
    {
        // No capability check here on purpose — the action holds it, so a
        // second caller cannot arrive ungated. See its own doc comment.
        $rotate(auth('staff')->user());

        return back();
    }

    private function authorizeInbox(): void
    {
        abort_unless(
            auth('staff')->user()?->role->allows(Capability::ManageMessages) ?? false,
            403,
        );
    }
}
