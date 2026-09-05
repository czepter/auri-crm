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
        public string $status,
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
            status: $shoot->status->key,
            updatedAt: $shoot->updated_at->toIso8601String(),
        );
    }
}
