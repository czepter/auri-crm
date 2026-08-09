<?php

namespace App\Crm\Data;

use App\Crm\Models\Shoot;
use App\Settings\Models\StudioClosure;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One reason a date is not free — another shoot already standing on it, or
 * the studio being closed. `source` is what lets a caller say *why* rather
 * than just *that*: "conflicts with an existing shoot" reads differently
 * from "studio is closed: Weihnachtspause", and CheckDateAvailability (T-09)
 * is where both sources land in this one shape.
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
        /** 'shoot' or 'closure'. */
        public string $source,
        public ?string $projectId,
        public ?string $projectName,
        public ?string $venue,
        public ?string $kind,
        /** The closure's label — set only when source is 'closure'. */
        public ?string $label,
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
}
