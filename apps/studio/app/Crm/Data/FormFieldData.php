<?php

namespace App\Crm\Data;

use App\Crm\Models\FormField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormFieldData extends Data
{
    /** @param ?array<array-key, mixed> $options */
    public function __construct(
        public string $id,
        public string $label,
        public string $type,
        public string $typeLabel,
        public bool $required,
        public ?string $help,
        public ?array $options,
    ) {}

    public static function fromModel(FormField $field): self
    {
        return new self(
            id: $field->public_id,
            label: $field->label,
            type: $field->type->value,
            typeLabel: $field->type->label(),
            required: $field->required,
            help: $field->help,
            options: $field->options,
        );
    }
}
