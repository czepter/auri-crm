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
        /** C-25: keyed by ProjectField::$key — see GET /project-types for what each one means. @var array<string, mixed> */
        public array $fields,
        public string $updatedAt,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->key,
            portalVisible: $project->portal_visible,
            fields: $project->field_values ?? [],
            updatedAt: $project->updated_at->toIso8601String(),
        );
    }
}
