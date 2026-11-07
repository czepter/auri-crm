<?php

namespace App\Engagement\Actions;

use App\Crm\Actions\NotifyStudio;
use App\Crm\Models\Contact;
use App\Engagement\Jobs\SendScheduledMessage;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Engagement\Notifications\Studio\ClientMessagePosted;
use App\Models\User;
use App\Support\LiveUpdate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A reply on an existing thread, from either side — the client continuing
 * their own conversation, or staff answering it. Never what creates the
 * thread itself; see StartThread for the client's own action that does.
 *
 * Writes a ThreadParticipant the first time this actor posts to this thread
 * (firstOrCreate, so a second reply from the same person is a no-op here) —
 * the record of who is actually on the conversation, kept as a side effect
 * of participating rather than through a membership screen of its own.
 */
class PostMessage
{
    public function __construct(
        private readonly SendStaffReply $send,
        private readonly NotifyStudio $notifyStudio,
    ) {}

    public function __invoke(Thread $thread, Contact|User $actor, string $body, ?CarbonInterface $scheduledFor = null): Message
    {
        $message = DB::transaction(function () use ($thread, $actor, $body, $scheduledFor) {
            $thread->participants()->firstOrCreate(
                $actor instanceof Contact ? ['contact_id' => $actor->id] : ['user_id' => $actor->id]
            );

            return $thread->messages()->create([
                ...$actor instanceof Contact ? ['contact_id' => $actor->id] : ['author_id' => $actor->id],
                'body' => $body,
                'scheduled_for' => $scheduledFor,
            ]);
        });

        LiveUpdate::publishThread($thread);

        // E-11 / E-13. Outside the transaction on purpose: a mail that has
        // already left cannot be rolled back, so it must not be sent from
        // inside one that still could. Staff only — a client's own message is
        // already where the studio reads it.
        if ($actor instanceof User) {
            $scheduledFor === null
                ? ($this->send)($message)
                : SendScheduledMessage::dispatch($message, $scheduledFor->toIso8601String())->delay($scheduledFor);
        } else {
            // T-11: the other direction. "Where the studio reads it" above is
            // a screen somebody has to open — this is what says to open it.
            ($this->notifyStudio)(new ClientMessagePosted($message));
        }

        return $message;
    }
}
