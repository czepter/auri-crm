<?php

namespace App\Crm\Data;

use App\Crm\Models\FormTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormTemplateData extends Data
{
    /** @param list<FormFieldData> $fields */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $projectType,
        public ?string $projectTypeLabel,
        public bool $isActive,
        public int $fieldCount,
        public string $updatedAt,
        public array $fields,
    ) {}

    public static function fromModel(FormTemplate $template): self
    {
        $fields = $template->fields;

        return new self(
            id: $template->public_id,
            name: $template->name,
            projectType: $template->project_type?->key,
            projectTypeLabel: $template->project_type?->name,
            isActive: $template->is_active,
            // fields_count comes from withCount('fields') on the index query,
            // the same shape as PackageData::itemCount. The edit screen loads
            // the fields anyway, and counts those.
            fieldCount: (int) ($template->fields_count ?? $fields->count()),
            updatedAt: $template->updated_at->toIso8601String(),
            fields: $fields->map(FormFieldData::fromModel(...))->all(),
        );
    }
}
