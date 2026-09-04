<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Project;
use App\Quotes\Models\Quote;

class QuoteAccepted extends StudioEventNotification
{
    public function __construct(private readonly Quote $quote) {}

    public function title(): string
    {
        return 'Angebot angenommen';
    }

    public function body(): string
    {
        return "{$this->quote->project->name} — {$this->quote->number}";
    }

    public function url(): string
    {
        return "/auftraege/{$this->quote->project->public_id}/angebote";
    }

    public function project(): ?Project
    {
        return $this->quote->project;
    }
}
