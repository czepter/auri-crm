<?php

namespace App\Engagement\Actions;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A line of the conversation that was said somewhere else — a mail before the
 * inbound address existed, a phone call, a chat — written into the thread
 * after the fact.
 *
 * Unlike PostMessage nothing leaves and nobody is notified: the words were
 * already delivered where they were said, so a mail now would repeat them.
 * That is also why this may open a thread, which StartThread otherwise keeps
 * for the client: the client did start the conversation, just not here.
 */
class RecordMessage
{
    public function __invoke(ClientCase $project, Contact|User $author, string $body, CarbonInterface $sentAt): Message
    {
        return DB::transaction(function () use ($project, $author, $body, $sentAt) {
            $thread = $project->latestThread()->first()
                ?? Thread::create(['case_id' => $project->id, 'subject' => $project->name]);

            $thread->participants()->firstOrCreate(
                $author instanceof Contact ? ['contact_id' => $author->id] : ['user_id' => $author->id]
            );

            $message = $thread->messages()->make([
                ...$author instanceof Contact ? ['contact_id' => $author->id] : ['author_id' => $author->id],
                'body' => $body,
            ]);
            // The column is written without an offset and read back in the
            // app's zone, so a time sent as +02:00 would land two hours off.
            $message->forceFill(['created_at' => $sentAt->copy()->setTimezone(config('app.timezone'))])->save();

            return $message;
        });
    }

    /** The named contact, or the one the project calls its main contact. */
    public function contact(ClientCase $project, ?string $publicId): ?Contact
    {
        if ($publicId !== null) {
            return $project->contacts()->where('contacts.public_id', $publicId)->first();
        }

        return $project->contacts()->wherePivot('role', CaseContactRole::Primary->value)->first()
            ?? $project->contacts()->first();
    }
}
