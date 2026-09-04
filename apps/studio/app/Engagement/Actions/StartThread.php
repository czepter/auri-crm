<?php

namespace App\Engagement\Actions;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Thread;
use App\Notifications\Actions\NotifyStudio;
use App\Notifications\Studio\ClientMessagePosted;
use Illuminate\Support\Facades\DB;

/**
 * The client's own action, and the only way a Thread comes to exist — E-06's
 * whole point. Staff reply to a thread (see PostMessage) but never originate
 * one; there is no admin screen that starts a conversation on the client's
 * behalf.
 *
 * P-04 (#223): $project is optional. A client who has not booked anything
 * yet, or who is asking about something no booking covers, still has one
 * studio to write to — the reference is what the thread carries, not what it
 * needs before it may exist. AttachThreadToProject supplies one later.
 */
class StartThread
{
    public function __construct(private readonly NotifyStudio $notifyStudio) {}

    public function __invoke(?Project $project, Contact $contact, string $subject, string $body): Thread
    {
        [$thread, $message] = DB::transaction(function () use ($project, $contact, $subject, $body) {
            $thread = Thread::create(['project_id' => $project?->id, 'subject' => $subject]);

            $thread->participants()->create(['contact_id' => $contact->id]);

            return [$thread, $thread->messages()->create(['contact_id' => $contact->id, 'body' => $body])];
        });

        // Outside the transaction, same rule PostMessage's own note gives.
        ($this->notifyStudio)(new ClientMessagePosted($message));

        return $thread;
    }
}
