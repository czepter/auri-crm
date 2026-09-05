<?php

namespace App\Integration\Data;

use App\Crm\Models\ProjectField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * C-25: one field definition as the api surface hands it out — the shape an
 * integration needs to know what a project of this type can carry, and what a
 * value written back under `key` has to look like.
 */
#[TypeScript]
class ProjectFieldReadData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public string $type,
        /** @var list<string> */
        public array $options,
        public ?string $unit,
        public bool $required,
        public bool $allowNewOptions,
    ) {}

    public static function fromModel(ProjectField $field): self
    {
        return new self(
            id: $field->public_id,
            key: $field->key,
            label: $field->label,
            type: $field->type->value,
            options: $field->options ?? [],
            unit: $field->unit,
            required: $field->required,
            allowNewOptions: $field->allow_new_options,
        );
    }
}
