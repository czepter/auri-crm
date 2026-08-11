<?php

namespace App\Crm\Data;

use App\Crm\Models\FormInstance;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormInstanceData extends Data
{
    /** @param list<FormInstanceFieldData> $fields */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $sourceTemplateName,
        public string $updatedAt,
        public array $fields,
    ) {}

    public static function fromModel(FormInstance $instance): self
    {
        return new self(
            id: $instance->public_id,
            name: $instance->name,
            sourceTemplateName: $instance->source_template_name,
            updatedAt: $instance->updated_at->toIso8601String(),
            fields: $instance->fields->map(FormInstanceFieldData::fromModel(...))->all(),
        );
    }
}
