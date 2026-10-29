<?php

namespace App\Crm\Notifications\Studio;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;

class InquiryReceived extends StudioEventNotification
{
    public function __construct(private readonly Lead $lead) {}

    public function title(): string
    {
        return 'Neue Anfrage';
    }

    public function body(): string
    {
        return $this->lead->case->primaryContact()?->name ?? $this->lead->case->name;
    }

    public function url(): string
    {
        return "/anfragen/{$this->lead->case->public_id}";
    }

    public function case(): ?ClientCase
    {
        return $this->lead->case;
    }
}
