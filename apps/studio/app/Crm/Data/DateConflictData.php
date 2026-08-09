<?php

namespace App\Crm\Data;

use App\Crm\Models\Shoot;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One other shoot already standing on a date.
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
        public string $projectId,
        public string $projectName,
        public ?string $venue,
        public string $kind,
    ) {}

    public static function fromShoot(Shoot $shoot): self
    {
        return new self(
            projectId: $shoot->project->public_id,
            projectName: $shoot->project->name,
            venue: $shoot->venue(),
            kind: $shoot->kind->label(),
        );
    }
}
