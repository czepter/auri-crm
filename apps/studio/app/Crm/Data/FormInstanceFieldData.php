<?php

namespace App\Crm\Data;

use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormInstanceField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormInstanceFieldData extends Data
{
    /**
     * @param  ?list<string>  $options
     * @param  ?list<string>  $fileFormats
     * @param  list<FormInstanceFileData>  $files
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $type,
        public string $typeLabel,
        public bool $required,
        public ?array $options,
        public ?string $currentAnswer,
        public ?string $answeredAt,
        // Only a "Datei" field fills these; everything else leaves them null
        // and its answer in currentAnswer, where it has always been.
        public ?array $fileFormats,
        public ?int $fileMaxSize,
        public bool $fileMultiple,
        public array $files,
    ) {}

    public static function fromModel(FormInstanceField $field): self
    {
        $isFile = $field->type === FormFieldType::File;
        $fileOptions = $isFile ? $field->fileOptions() : null;
        $files = $isFile ? $field->files : collect();

        return new self(
            id: $field->public_id,
            label: $field->label,
            type: $field->type->value,
            typeLabel: $field->type->label(),
            required: $field->required,
            options: $isFile ? null : $field->options,
            currentAnswer: $field->currentAnswer?->value,
            // A file field is answered by having a file, so the progress the
            // studio reads counts one the same way it counts the other.
            answeredAt: $isFile
                ? $files->last()?->created_at->toIso8601String()
                : $field->currentAnswer?->created_at->toIso8601String(),
            fileFormats: $fileOptions['formats'] ?? null,
            fileMaxSize: $fileOptions['maxSize'] ?? null,
            fileMultiple: $fileOptions['multiple'] ?? false,
            files: $files->map(FormInstanceFileData::fromModel(...))->all(),
        );
    }
}
