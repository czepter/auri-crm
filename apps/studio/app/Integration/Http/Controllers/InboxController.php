<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Engagement\Actions\AttachThreadToCase;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Data\MessageData;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The api surface's half of E-12/P-04 — the same PostMessage, AttachThreadToCase
 * and workState() the admin Posteingang (App\Engagement\Http\Controllers\InboxController)
 * uses, reused rather than duplicated.
 */
class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $threads = Thread::query()
            ->when($request->user('api') instanceof User, fn ($query) => $query->where(
                fn ($visible) => $visible->whereDoesntHave('case')
                    ->orWhereHas('case', fn ($case) => $case->visibleTo($request->user('api')))
            ))
            ->with(['case', 'latestMessage', 'participants.contact'])
            ->withExists(['messages as from_mail' => fn ($query) => $query->whereNotNull('contact_id')])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $threads->map(fn (Thread $thread) => $this->row($thread))->values()]);
    }

    public function reply(Request $request, Thread $thread, PostMessage $post): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $actor = $this->actor($request);

        if ($actor === null) {
            throw ValidationException::withMessages(['actor' => 'Nachrichten senden braucht einen Zugang mit Mitarbeiter.']);
        }

        $message = $post($thread, $actor, $validated['body']);

        LiveUpdate::publish();

        return response()->json(MessageData::fromModel($message->load('author')), 201);
    }

    public function close(Thread $thread): JsonResponse
    {
        $thread->forceFill([
            'closed_message_id' => $thread->workState() === 'erledigt' ? null : $thread->latestMessage?->id,
        ])->save();

        return response()->json(['id' => $thread->public_id, 'state' => $thread->fresh()->workState()]);
    }

    public function attach(Request $request, InboundMessage $inboundMessage, PostMessage $post): JsonResponse
    {
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

        LiveUpdate::publish();

        return response()->json(MessageData::fromModel($message->load('contact')), 201);
    }

    public function file(Request $request, Thread $thread, AttachThreadToCase $attach): JsonResponse
    {
        $validated = $request->validate(['case' => ['required', 'string']]);

        $project = ClientCase::query()->where('public_id', $validated['case'])->firstOrFail();

        try {
            $attach($thread, $project);
        } catch (LogicException $e) {
            throw ValidationException::withMessages(['thread' => $e->getMessage()]);
        }

        return response()->json(['id' => $thread->public_id, 'projectId' => $project->public_id]);
    }

    /** Mirrors the admin Posteingang's own row — see its doc comment. */
    private function row(Thread $thread): array
    {
        $latest = $thread->latestMessage;

        return [
            'id' => $thread->public_id,
            'projectId' => $thread->case?->public_id,
            'projectName' => $thread->case?->name,
            'subject' => $thread->subject,
            'who' => $thread->participants
                ->map(fn ($participant) => $participant->contact?->name)
                ->filter()
                ->join(', ') ?: ($thread->case?->name ?? ''),
            'snippet' => $latest?->body,
            'state' => $thread->workState(),
            'waitingDays' => $latest === null ? null : (int) $latest->created_at->diffInDays(now()),
            'origin' => $thread->from_mail ? 'mail' : 'portal',
        ];
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
