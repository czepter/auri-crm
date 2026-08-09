<?php

namespace App\Engagement\Actions;

use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Models\User;
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
    public function __invoke(Thread $thread, Contact|User $actor, string $body): Message
    {
        return DB::transaction(function () use ($thread, $actor, $body) {
            $thread->participants()->firstOrCreate(
                $actor instanceof Contact ? ['contact_id' => $actor->id] : ['user_id' => $actor->id]
            );

            return $thread->messages()->create([
                ...$actor instanceof Contact ? ['contact_id' => $actor->id] : ['author_id' => $actor->id],
                'body' => $body,
            ]);
        });
    }
}
