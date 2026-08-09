<?php

namespace App\Crm\Data;

use App\Crm\Models\FormInstanceField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormInstanceFieldData extends Data
{
    /** @param ?list<string> $options */
    public function __construct(
        public string $id,
        public string $label,
        public string $type,
        public string $typeLabel,
        public bool $required,
        public ?array $options,
        public ?string $currentAnswer,
        public ?string $answeredAt,
    ) {}

    public static function fromModel(FormInstanceField $field): self
    {
        return new self(
            id: $field->public_id,
            label: $field->label,
            type: $field->type->value,
            typeLabel: $field->type->label(),
            required: $field->required,
            options: $field->options,
            currentAnswer: $field->currentAnswer?->value,
            answeredAt: $field->currentAnswer?->created_at->toIso8601String(),
        );
    }
}
