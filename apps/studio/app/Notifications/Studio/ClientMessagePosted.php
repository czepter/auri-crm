<?php

namespace App\Notifications\Studio;

use App\Crm\Models\ClientCase;
use App\Engagement\Models\Message;

class ClientMessagePosted extends StudioEventNotification
{
    public function __construct(private readonly Message $message) {}

    public function title(): string
    {
        return 'Neue Nachricht';
    }

    public function body(): string
    {
        return $this->message->thread->case?->name ?? 'Portal';
    }

    /* A thread that names no booking yet is read in the Posteingang — the
       same split /posteingang/unterhaltung/{thread} already makes. */
    public function url(): string
    {
        $project = $this->message->thread->case;

        return $project === null ? '/posteingang' : "/auftraege/{$project->public_id}/nachrichten";
    }

    public function case(): ?ClientCase
    {
        return $this->message->thread->case;
    }
}
