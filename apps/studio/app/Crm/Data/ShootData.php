<?php

namespace App\Crm\Data;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One shooting occasion as the project screen reads it.
 *
 * `forwardStatuses` is computed here rather than in the component: forward-only
 * with skipping allowed is a domain rule, and a component that filtered the
 * nine cases itself would be a second copy of it drifting from the enum.
 */
#[TypeScript]
class ShootData extends Data
{
    /**
     * @param  list<CrewMemberData>  $crew
     * @param  list<array{value: string, label: string}>  $forwardStatuses
     * @param  list<TimelineEntryData>  $timeline
     * @param  list<DateConflictData>  $crewConflicts
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $label,
        public ?string $date,
        public ?string $callTime,
        public ?string $endTime,
        public ?string $venue,
        // Beside the joined line the two parts it was joined from: the edit
        // state writes them separately, and splitting the line back apart on
        // the client cut "Berlin, Mitte" in the wrong place.
        public ?string $venueName,
        public ?string $venueCity,
        public string $status,
        public string $statusLabel,
        public bool $isPrimary,
        public bool $isCancelled,
        public ?string $cancelledReason,
        public array $crew,
        public array $forwardStatuses,
        public array $timeline,
        public string $pdfUrl,
        public string $icsUrl,
        public array $crewConflicts,
    ) {}

    /**
     * T-10: $conflicts is CheckDateAvailability's own list, already narrowed
     * by the caller to this shoot's date and this shoot's crew — see
     * CaseController::shootsOf(). S-02's picker warns off it; it never
     * blocks the assignment, same as every other source that action produces.
     *
     * @param  list<DateConflictData>  $conflicts
     */
    public static function fromShoot(Shoot $shoot, array $conflicts = []): self
    {
        // Nothing has been shot before the day: from "Fotografiert" on, a
        // future shoot offers only archiving.
        $shotAt = $shoot->date?->isFuture() ? Stage::anchored(StagePipeline::Project, StageAnchor::Shot)?->sort_order : null;

        return new self(
            id: $shoot->public_id,
            kind: $shoot->kind,
            label: $shoot->label,
            date: $shoot->date?->format('Y-m-d'),
            // H:i, not the H:i:s the column returns — a call time is a clock
            // reading and the seconds are noise on every screen that shows it.
            callTime: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            endTime: $shoot->end_time === null ? null : substr((string) $shoot->end_time, 0, 5),
            venue: $shoot->venue(),
            venueName: $shoot->venue_name,
            venueCity: $shoot->venue_city,
            status: $shoot->status->key,
            statusLabel: $shoot->status->name,
            isPrimary: $shoot->is_primary,
            isCancelled: $shoot->isCancelled(),
            cancelledReason: $shoot->cancelled_reason,
            crew: $shoot->crew
                ->map(fn ($assignment) => CrewMemberData::fromAssignment($assignment))
                ->all(),
            forwardStatuses: $shoot->isCancelled() ? [] : app(CrmVocabulary::class)
                ->ordered(StagePipeline::Project)
                ->filter(fn (Stage $status) => $status->is_active && $shoot->status->canTransitionTo($status))
                ->reject(fn (Stage $status) => $shotAt !== null && $status->sort_order >= $shotAt && $status->anchor !== StageAnchor::Archived)
                ->map(fn (Stage $status) => ['value' => $status->key, 'label' => $status->name])
                ->values()
                ->all(),
            timeline: $shoot->timeline->map(TimelineEntryData::fromEntry(...))->all(),
            pdfUrl: "/shootings/{$shoot->public_id}/ablauf.pdf",
            icsUrl: "/shootings/{$shoot->public_id}/ablauf.ics",
            crewConflicts: $conflicts,
        );
    }
}
