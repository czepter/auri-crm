<?php

namespace App\Crm\Data;

use App\Crm\Models\Lead;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row in the queue pane: who, when, how long they have waited, and enough
 * of the inquiry to recognise it. The card beside it carries the rest.
 */
#[TypeScript]
class LeadQueueRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $date,
        public ?string $snippet,
        public bool $open,
        public int $waitingDays,
    ) {}

    public static function fromLead(Lead $lead, ?Lead $open = null): self
    {
        return new self(
            id: $lead->project->public_id,
            name: $lead->project->contactNames(),
            date: $lead->project->primaryShoot?->date?->format('Y-m-d'),
            // Two lines at the pane's width. Longer runs under the next row
            // and the group stops being scannable.
            snippet: Str::limit((string) $lead->inquiry_text, 90) ?: null,
            open: $open !== null && $lead->public_id === $open->public_id,
            waitingDays: (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
        );
    }
}
