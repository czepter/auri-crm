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
        // P-03 (#222): shared with the client, or the studio's own checklist.
        // Both live on this table; the stamp is the whole difference.
        public ?string $sharedAt,
        public array $fields,
    ) {}

    public static function fromModel(FormInstance $instance): self
    {
        return new self(
            id: $instance->public_id,
            name: $instance->name,
            sourceTemplateName: $instance->source_template_name,
            updatedAt: $instance->updated_at->toIso8601String(),
            sharedAt: $instance->shared_at?->toIso8601String(),
            fields: $instance->fields->map(FormInstanceFieldData::fromModel(...))->all(),
        );
    }
}
