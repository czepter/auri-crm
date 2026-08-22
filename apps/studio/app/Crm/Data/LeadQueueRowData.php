<?php

namespace App\Crm\Data;

use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row in the queue pane: who, when, how long they have waited, and what
 * they wrote. The card beside it carries the rest.
 */
#[TypeScript]
class LeadQueueRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $date,
        public ?string $text,
        public bool $open,
        public int $waitingDays,
    ) {}

    public static function fromLead(Lead $lead, ?Lead $open = null): self
    {
        return new self(
            // The lead's own id: the pane links to /anfragen/{lead}, and the
            // project's id 404s there.
            id: $lead->public_id,
            name: $lead->project->contactNames(),
            date: $lead->project->primaryShoot?->date?->format('Y-m-d'),
            // Whole text, not the two lines the pane draws: the search in
            // the pane reads this and would otherwise stop at 90 characters.
            // The row clamps its own height.
            text: $lead->inquiry_text ?: null,
            open: $open !== null && $lead->public_id === $open->public_id,
            waitingDays: (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
        );
    }
}
