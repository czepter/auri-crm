<?php

namespace App\Crm\Data;

use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Settings\Models\StudioClosure;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One reason a date is not free — another shoot already standing on it, the
 * studio being closed, or one person being away. `source` is what lets a
 * caller say *why* rather than just *that*: "conflicts with an existing
 * shoot" reads differently from "studio is closed: Weihnachtspause", and
 * CheckDateAvailability (T-09, T-10) is where every source lands in this one
 * shape.
 *
 * Never persisted. A stored has_conflict flag would be a lie the moment the
 * colliding project moves its date or goes lost, and there is no event on that
 * other project anything would think to subscribe to. Deriving it makes the
 * flag self-healing, for the same reason vision §7 gives for the production
 * board: no rollup column to drift and no reconciliation job.
 */
#[TypeScript]
class DateConflictData extends Data
{
    public function __construct(
        /** 'shoot', 'closure' or 'absence'. */
        public string $source,
        public ?string $projectId,
        public ?string $projectName,
        public ?string $venue,
        public ?string $kind,
        /** The closure's label, or the absent person's name and category — set for 'closure' and 'absence'. */
        public ?string $label,
        /** The away person's public_id — set only when source is 'absence', so S-02's picker can match a specific assignee. */
        public ?string $personId = null,
    ) {}

    public static function fromShoot(Shoot $shoot): self
    {
        return new self(
            source: 'shoot',
            projectId: $shoot->project->public_id,
            projectName: $shoot->project->name,
            venue: $shoot->venue(),
            kind: $shoot->kind->label(),
            label: null,
        );
    }

    public static function fromClosure(StudioClosure $closure): self
    {
        return new self(
            source: 'closure',
            projectId: null,
            projectName: null,
            venue: null,
            kind: null,
            label: $closure->label,
        );
    }

    /**
     * T-10. Never carries `category` on its own — only this one composed
     * label string, the same shape a closure's own label already takes, so a
     * caller can never pick the category back apart and forward it
     * somewhere this data was never meant to reach (the portal, an ICS feed).
     */
    public static function fromAbsence(Absence $absence): self
    {
        return new self(
            source: 'absence',
            projectId: null,
            projectName: null,
            venue: null,
            kind: null,
            label: $absence->user->name.' · '.$absence->category->label(),
            personId: $absence->user->public_id,
        );
    }
}
