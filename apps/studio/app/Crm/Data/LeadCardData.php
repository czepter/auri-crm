<?php

namespace App\Crm\Data;

use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a lead is triaged on, one fact per line, so a column scans down
 * consistent positions.
 *
 * The value is the latest sent quote's total, never an estimate: an
 * estimated_value column would be a second money number to reconcile against
 * the real quote later, and a placeholder zero is a row nobody can act on. A
 * lead with nothing sent carries null and the column shows a dash.
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
        // C-07 (#241): the list view carries a value column and the board's
        // column heads carry the sum of one — the question staff otherwise
        // leave the screen for. Minor units, formatted once on the screen.
        // Null where nothing has been sent: a quote that does not exist is
        // not a zero.
        public ?int $value,
        public string $lastAt,
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
            names: $project->contactNames(),
            date: $primary?->date?->format('Y-m-d'),
            shootCount: $project->shoots->count(),
            venue: $primary?->venue(),
            source: $lead->source->label(),
            stage: $lead->stage->value,
            daysInStage: (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            value: $project->latestQuote?->total,
            lastAt: $lead->stage_entered_at->toIso8601String(),
            conflicts: $conflicts,
        );
    }
}
