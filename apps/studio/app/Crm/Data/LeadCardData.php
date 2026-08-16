<?php

namespace App\Crm\Data;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a lead is triaged on, one fact per line, so a column scans down
 * consistent positions.
 *
 * No value and no column sum: Quote is lead-scoped and arrives with P2, and the
 * two alternatives were both worse — an estimated_value column is a second
 * money number to reconcile against the real quote later, and a placeholder
 * zero is a row nobody can act on. The card still triages, because the age and
 * its stale colouring are what the board exists to surface.
 */
#[TypeScript]
class LeadCardData extends Data
{
    /** @param  list<DateConflictData>  $conflicts */
    public function __construct(
        public string $id,
        // The lead's own id, beside the project's. The card links to the
        // project and posts stage changes to the lead, and the two public ids
        // are not interchangeable — one field each is what keeps them apart.
        public string $leadId,
        public string $type,
        public string $names,
        public ?string $date,
        public int $shootCount,
        public ?string $venue,
        public string $source,
        public string $stage,
        public int $daysInStage,
        public array $conflicts,
    ) {}

    /** @param  list<DateConflictData>  $conflicts */
    public static function fromLead(Lead $lead, array $conflicts): self
    {
        $project = $lead->project;
        $primary = $project->primaryShoot;

        return new self(
            id: $project->public_id,
            leadId: $lead->public_id,
            type: $project->type->label(),
            // Primary contact first, then the rest. Falls back to the project
            // name for a project whose contacts have not been attached yet.
            names: $project->contacts
                ->sortBy(fn ($contact) => $contact->pivot->role === ProjectContactRole::Primary->value ? 0 : 1)
                ->pluck('name')
                ->join(' & ') ?: $project->name,
            date: $primary?->date?->format('Y-m-d'),
            shootCount: $project->shoots->count(),
            venue: $primary?->venue(),
            source: $lead->source->label(),
            stage: $lead->stage->value,
            daysInStage: (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            conflicts: $conflicts,
        );
    }
}
