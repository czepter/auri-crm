<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Lead;
use App\Crm\Models\Project;

class InquiryReceived extends StudioEventNotification
{
    public function __construct(private readonly Lead $lead) {}

    public function title(): string
    {
        return 'Neue Anfrage';
    }

    public function body(): string
    {
        return $this->lead->project->primaryContact()?->name ?? $this->lead->project->name;
    }

    public function url(): string
    {
        return "/anfragen/{$this->lead->public_id}";
    }

    public function project(): ?Project
    {
        return $this->lead->project;
    }
}
