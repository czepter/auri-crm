<?php

namespace App\Integration\Data;

use App\Crm\Models\Shoot;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A shoot as I-02's read endpoints hand it to an api-surface token.
 * public_id only, for both the shoot and the project it hangs off — never
 * either row's internal id.
 */
#[TypeScript]
class ShootReadData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $kind,
        public ?string $label,
        public ?string $date,
        public ?string $callTime,
        public ?string $endTime,
        public ?string $venueName,
        public ?string $venueCity,
        public string $status,
        public ?string $cancelledAt,
        public ?string $cancelledReason,
        public string $updatedAt,
    ) {}

    /** Requires `project:id,public_id` already eager loaded — see CrmReadController. */
    public static function fromModel(Shoot $shoot): self
    {
        return new self(
            id: $shoot->public_id,
            projectId: $shoot->project->public_id,
            kind: $shoot->kind->value,
            label: $shoot->label,
            date: $shoot->date?->format('Y-m-d'),
            callTime: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            endTime: $shoot->end_time === null ? null : substr((string) $shoot->end_time, 0, 5),
            venueName: $shoot->venue_name,
            venueCity: $shoot->venue_city,
            status: $shoot->status->key,
            cancelledAt: $shoot->cancelled_at?->toIso8601String(),
            cancelledReason: $shoot->cancelled_reason,
            updatedAt: $shoot->updated_at->toIso8601String(),
        );
    }
}
