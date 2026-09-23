<?php

namespace App\Notifications\Studio;

use App\Crm\Models\ClientCase;
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
        return "{$this->quote->case->name} — {$this->quote->number}";
    }

    public function url(): string
    {
        return "/auftraege/{$this->quote->case->public_id}/angebote";
    }

    public function case(): ?ClientCase
    {
        return $this->quote->case;
    }
}
