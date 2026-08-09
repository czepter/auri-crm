<?php

namespace App\Crm\Data;

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Shoot;
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
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $label,
        public ?string $date,
        public ?string $callTime,
        public ?string $endTime,
        public ?string $venue,
        public string $status,
        public string $statusLabel,
        public bool $isPrimary,
        public bool $isCancelled,
        public ?string $cancelledReason,
        public array $crew,
        public array $forwardStatuses,
    ) {}

    public static function fromShoot(Shoot $shoot): self
    {
        return new self(
            id: $shoot->public_id,
            kind: $shoot->kind->label(),
            label: $shoot->label,
            date: $shoot->date?->format('Y-m-d'),
            // H:i, not the H:i:s the column returns — a call time is a clock
            // reading and the seconds are noise on every screen that shows it.
            callTime: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            endTime: $shoot->end_time === null ? null : substr((string) $shoot->end_time, 0, 5),
            venue: $shoot->venue(),
            status: $shoot->status->value,
            statusLabel: $shoot->status->label(),
            isPrimary: $shoot->is_primary,
            isCancelled: $shoot->isCancelled(),
            cancelledReason: $shoot->cancelled_reason,
            crew: $shoot->crew
                ->map(fn ($assignment) => CrewMemberData::fromAssignment($assignment))
                ->all(),
            forwardStatuses: $shoot->isCancelled() ? [] : collect(ShootStatus::cases())
                ->filter(fn (ShootStatus $status) => $shoot->status->canTransitionTo($status))
                ->map(fn (ShootStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->values()
                ->all(),
        );
    }
}
