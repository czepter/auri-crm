<?php

namespace App\Engagement\Http\Controllers;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationRunStatus;
use App\Automation\Models\AutomationRun;
use App\Crm\Models\ClientCase;
use App\Engagement\Actions\AttachThreadToCase;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Engagement\Support\InboundAddress;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Settings\Actions\RotateInboundSecret;
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
    /** The three groups, in the order the day is worked. */
    private const STATES = ['unbeantwortet', 'wartet', 'erledigt'];

    public function index(Request $request): Response
    {
        $this->authorizeInbox();

        $secret = StudioSettings::current()->inbound_secret;
        $user = auth('staff')->user();

        $threads = Thread::query()
            ->with(['case.primaryShoot', 'latestMessage', 'participants.contact', 'participants.user'])
            ->withExists(['messages as from_mail' => fn ($query) => $query->whereNotNull('contact_id')])
            ->latest()
            ->limit(200)
            ->get();

        $open = $this->open($request, $threads);

        return Inertia::render('Posteingang', [
            // C-14 (#250): conversations, grouped by working state. An inbox
            // sorted by clock time hides exactly what it is opened for.
            'groups' => collect(self::STATES)->map(fn (string $state) => [
                'key' => $state,
                'rows' => $threads
                    ->filter(fn (Thread $thread) => $thread->workState() === $state)
                    ->map(fn (Thread $thread) => $this->row($thread))
                    ->values()
                    ->all(),
            ])->all(),
            'open' => $open === null ? null : $this->conversation($open),
            'configured' => InboundAddress::configured(),
            // The studio's own name in the address, not a generic handle:
            // this is the one inbound address a studio prints on a website
            // or reads out over the phone.
            'catchAll' => $secret === null
                ? null
                : InboundAddress::catchAll(StudioSettings::current()->display_name ?? '', $secret),
            'canRotate' => $user?->can(Capability::ManageSettings->value) ?? false,
            'pending' => InboundMessage::query()
                ->whereIn('state', [InboundMessageState::Triage, InboundMessageState::Quarantined])
                ->with('thread.case')
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
                    'projectName' => $row->thread?->case?->name,
                ])
                ->all(),
            // P-04 (#223): conversations a client started without naming a
            // booking. They belong in the same tray as an unplaced mail — a
            // thing that arrived and has not been filed — and for the same
            // reason: the alternative is a conversation nobody at the studio
            // ever finds because no project screen lists it.
            'unfiled' => Thread::query()
                ->whereNull('case_id')
                ->with('latestMessage')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Thread $thread) => [
                    'id' => $thread->public_id,
                    'subject' => $thread->subject,
                    'snippet' => $thread->latestMessage?->body,
                    'startedAt' => $thread->created_at->toIso8601String(),
                ])
                ->all(),
            // #438: client mails an automation drafted, waiting for the studio's send.
            'drafts' => AutomationRun::query()
                ->where('status', AutomationRunStatus::AwaitingApproval)
                ->with('anchorable')
                ->latest('fired_at')
                ->limit(100)
                ->get()
                ->map(fn (AutomationRun $run) => [
                    'id' => $run->public_id,
                    'subject' => $run->subject,
                    'body' => $run->body,
                    'projectName' => $run->anchorable === null ? null : FireAutomationRule::projectOf($run->anchorable)?->name,
                    'to' => $run->contacts()->pluck('name')->join(', '),
                ])
                ->all(),
            'projects' => ClientCase::query()
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (ClientCase $project) => ['id' => $project->public_id, 'label' => $project->name])
                ->all(),
            // Only threads a staff member could plausibly attach to: the ones
            // with a client on them. A thread with no contact participant has
            // nobody the mail could have come from.
            'threads' => Thread::query()
                ->whereHas('participants', fn ($query) => $query->whereNotNull('contact_id'))
                ->with('case')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Thread $thread) => [
                    'id' => $thread->public_id,
                    'label' => ($thread->case?->name ?? '—').' · '.$thread->subject,
                ])
                ->all(),
        ]);
    }

    /** The conversation the screen is showing: what was asked for, else the first unanswered one. */
    private function open(Request $request, $threads): ?Thread
    {
        $asked = (string) $request->query('unterhaltung', '');

        return $threads->firstWhere('public_id', $asked)
            ?? $threads->first(fn (Thread $thread) => $thread->workState() === 'unbeantwortet')
            ?? $threads->first();
    }

    /**
     * One line in the queue. The project name stands under the sender rather
     * than the mail address: threads hang off a project, and which booking a
     * message is about is the thing a studio triages on.
     */
    private function row(Thread $thread): array
    {
        $latest = $thread->latestMessage;

        return [
            'id' => $thread->public_id,
            'who' => $thread->participants
                ->map(fn ($participant) => $participant->contact?->name)
                ->filter()
                ->join(', ') ?: ($thread->case?->name ?? ''),
            'project' => $thread->case?->name,
            'subject' => $thread->subject,
            'snippet' => $latest?->body,
            // Waiting time, in days. The screen decides when it is long
            // enough to colour — this only states it.
            'waitingDays' => $latest === null ? null : (int) $latest->created_at->diffInDays(now()),
            'origin' => $thread->from_mail ? 'mail' : 'portal',
        ];
    }

    /** The thread itself, its people and what the trace shows beside it. */
    private function conversation(Thread $thread): array
    {
        $thread->load(['messages.contact', 'messages.author']);

        return [
            'id' => $thread->public_id,
            'subject' => $thread->subject,
            'state' => $thread->workState(),
            'projectId' => $thread->case?->public_id,
            'projectName' => $thread->case?->name,
            'shootDate' => $thread->case?->primaryShoot?->date?->format('Y-m-d'),
            'origin' => $thread->from_mail ? 'mail' : 'portal',
            'people' => $thread->participants
                ->map(fn ($participant) => $participant->contact?->name ?? $participant->user?->name)
                ->filter()
                ->values()
                ->all(),
            'messages' => $thread->messages->map(fn ($message) => [
                'id' => $message->public_id,
                'who' => $message->contact?->name ?? $message->author?->name ?? '',
                'own' => $message->contact_id === null,
                'body' => $message->body,
                'at' => $message->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * A conversation nobody has to answer any more. Not derivable — one that
     * needs no reply looks exactly like one waiting for it — so it is a
     * decision, and a new message from the client undoes it.
     */
    public function close(Thread $thread): RedirectResponse
    {
        $this->authorizeInbox();

        $thread->forceFill([
            'closed_message_id' => $thread->workState() === 'erledigt' ? null : $thread->latestMessage?->id,
        ])->save();

        return back();
    }

    /** The studio's own reply, from the screen the conversation is read on. */
    public function reply(Request $request, Thread $thread, PostMessage $post): RedirectResponse
    {
        $this->authorizeInbox();

        $post($thread, auth('staff')->user(), $request->validate([
            'body' => ['required', 'string', 'max:20000'],
        ])['body']);

        return back();
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

    /**
     * P-04 (#223): the same movement one row up, for a thread rather than a
     * mail. AttachThreadToCase refuses to re-point one that already names
     * a booking — see its own doc comment.
     */
    public function file(Request $request, Thread $thread, AttachThreadToCase $attach): RedirectResponse
    {
        $this->authorizeInbox();

        $validated = $request->validate(['project' => ['required', 'string']]);

        $attach($thread, ClientCase::query()->where('public_id', $validated['project'])->firstOrFail());

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
            auth('staff')->user()?->can(Capability::ManageMessages->value) ?? false,
            403,
        );
    }
}
