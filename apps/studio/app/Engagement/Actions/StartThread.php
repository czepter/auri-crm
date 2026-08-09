<?php

namespace App\Engagement\Actions;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Models\Thread;
use Illuminate\Support\Facades\DB;

/**
 * The client's own action, and the only way a Thread comes to exist — E-06's
 * whole point. Staff reply to a thread (see PostMessage) but never originate
 * one; there is no admin screen that starts a conversation on the client's
 * behalf.
 */
class StartThread
{
    public function __invoke(Project $project, Contact $contact, string $subject, string $body): Thread
    {
        return DB::transaction(function () use ($project, $contact, $subject, $body) {
            $thread = $project->threads()->create(['subject' => $subject]);

            $thread->participants()->create(['contact_id' => $contact->id]);
            $thread->messages()->create(['contact_id' => $contact->id, 'body' => $body]);

            return $thread;
        });
    }
}
