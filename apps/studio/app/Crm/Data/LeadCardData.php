<?php

namespace App\Crm\Data;

use App\Crm\Enums\StageAnchor;
use App\Crm\Models\Lead;
use App\Support\FibonacciReminderSchedule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a lead is triaged on, one fact per line, so a column scans down
 * consistent positions.
 *
 * The value is the current quote's total, draft or sent, never an estimate: an
 * estimated_value column would be a second money number to reconcile against
 * the real quote later, and a placeholder zero is a row nobody can act on. A
 * lead without a quote carries null and the column shows a dash.
 */
#[TypeScript]
class LeadCardData extends Data
{
    /** @param  list<DateConflictData>  $conflicts */
    public function __construct(
        public string $id,
        public string $type,
        // Der Schluessel neben dem Namen: das Board faerbt seinen Punkt danach,
        // und den Namen darf das Studio aendern.
        public string $typeKey,
        public string $names,
        public ?string $date,
        public int $shootCount,
        public ?string $venue,
        public ?string $source,
        public string $stage,
        public int $daysInStage,
        // Liegezeit, abgeleitet statt gespeichert: ein zweiter Status neben
        // der Stufe liefe mit ihr auseinander, sobald jemand die Karte
        // verschiebt. Die Schwelle ist die erste Sprosse des Laufs, der
        // ohnehin schon erinnert — siehe RemindStalledLeads.
        public bool $stale,
        // C-07 (#241): the list view carries a value column and the board's
        // column heads carry the sum of one — the question staff otherwise
        // leave the screen for. Minor units, formatted once on the screen.
        // Null where there is no quote: a quote that does not exist is
        // not a zero.
        public ?int $value,
        public string $lastAt,
        public array $conflicts,
    ) {}

    /** @param  list<DateConflictData>  $conflicts */
    public static function fromLead(Lead $lead, array $conflicts): self
    {
        $project = $lead->case;
        $primary = $project->primaryShoot;
        $daysInStage = (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay());

        return new self(
            id: $project->public_id,
            type: $project->type->name,
            typeKey: $project->type->key,
            names: $project->contactNames(),
            date: $primary?->date?->format('Y-m-d'),
            shootCount: $project->shoots->count(),
            venue: $primary?->venue(),
            source: $lead->source?->label(),
            stage: $lead->stage->key,
            daysInStage: $daysInStage,
            stale: $lead->stage->anchor !== StageAnchor::Won
                && $daysInStage >= FibonacciReminderSchedule::FIRST_RUNG,
            value: $project->currentQuote?->total,
            lastAt: $lead->stage_entered_at->toIso8601String(),
            conflicts: $conflicts,
        );
    }
}
