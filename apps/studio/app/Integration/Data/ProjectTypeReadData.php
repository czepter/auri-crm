<?php

namespace App\Integration\Data;

use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * C-25: a project type with the fields it defines. Inactive fields are left
 * out — a caller may only write what a studio still asks for.
 */
#[TypeScript]
class ProjectTypeReadData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public bool $isActive,
        public bool $isDefault,
        /** @var list<ProjectFieldReadData> */
        public array $fields,
    ) {}

    public static function fromModel(ProjectType $type): self
    {
        return new self(
            id: $type->public_id,
            key: $type->key,
            name: $type->name,
            isActive: $type->is_active,
            isDefault: $type->is_default,
            fields: $type->fields
                ->filter(fn (ProjectField $field) => $field->is_active)
                ->map(ProjectFieldReadData::fromModel(...))
                ->values()
                ->all(),
        );
    }
}
