<?php

namespace App\Integration\Data;

use App\Crm\Models\Project;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A project as I-02's read endpoints hand it to an api-surface token.
 * public_id only — see HasPublicId — never the row's internal id.
 */
#[TypeScript]
class ProjectReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public bool $portalVisible,
        public string $updatedAt,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->value,
            portalVisible: $project->portal_visible,
            updatedAt: $project->updated_at->toIso8601String(),
        );
    }
}
