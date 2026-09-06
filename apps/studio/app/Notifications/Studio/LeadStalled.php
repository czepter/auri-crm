<?php

namespace App\Notifications\Studio;

use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use Illuminate\Support\Collection;

/**
 * T-20: the inquiries that have not moved. One message per recipient with
 * every stalled lead on it — nine untouched inquiries are one thing to look
 * at, not nine mails.
 *
 * @param  Collection<int, Lead>  $leads
 */
class LeadStalled extends StudioEventNotification
{
    public function __construct(private readonly Collection $leads) {}

    public function title(): string
    {
        return 'Liegengebliebene Anfragen';
    }

    public function body(): string
    {
        return implode(' · ', $this->lines());
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->leads
            ->map(fn (Lead $lead) => sprintf(
                '%s — %s, seit %d Tagen',
                $lead->project->name,
                $lead->stage->name,
                (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            ))
            ->values()
            ->all();
    }

    public function url(): string
    {
        return '/anfragen';
    }

    /* Several projects at once, so no single one — the recipients were
       already resolved when the scan grouped them. */
    public function project(): ?Project
    {
        return null;
    }
}
