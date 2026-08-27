<?php

namespace App\Crm\Enums;

use Illuminate\Validation\Rule;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * E-01's builder palette. Every case in this enum is a button in it — see
 * mockup 13's .qpal.
 */
#[TypeScript]
enum FormFieldType: string implements HasLabel
{
    case Text = 'text';
    case ShortText = 'short_text';
    case Boolean = 'boolean';
    case Select = 'select';
    case Date = 'date';
    case Time = 'time';
    case NameList = 'name_list';
    case File = 'file';

    /** What a "Datei" field may be configured to accept. Nothing outside this list is storable. */
    public const FILE_FORMATS = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'webp', 'docx'];

    public const FILE_MAX_MB = 25;

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Freitext',
            self::ShortText => 'Kurzantwort',
            self::Boolean => 'Ja / Nein',
            self::Select => 'Auswahl',
            self::Date => 'Datum',
            self::Time => 'Uhrzeit',
            self::NameList => 'Namensliste',
            self::File => 'Datei',
        };
    }

    /** "select" and "file" read FormField.options — everything else leaves it null. */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::File;
    }

    /**
     * Rules for one field's options, keyed by payload path. Two shapes share
     * the one json column: select's list of choices, file's upload config.
     * Nullable throughout because a field may arrive from a template that
     * never configured it — FormInstanceField::fileOptions() fills the rest.
     *
     * @return array<string, list<mixed>>
     */
    public function optionRules(string $prefix): array
    {
        if ($this !== self::File) {
            return [
                "{$prefix}.options" => ['nullable', 'array'],
                "{$prefix}.options.*" => ['string', 'max:255'],
            ];
        }

        return [
            "{$prefix}.options" => ['nullable', 'array'],
            "{$prefix}.options.formats" => ['nullable', 'array', 'min:1'],
            "{$prefix}.options.formats.*" => ['string', Rule::in(self::FILE_FORMATS)],
            "{$prefix}.options.maxSize" => ['nullable', 'integer', 'min:1', 'max:'.self::FILE_MAX_MB],
            "{$prefix}.options.multiple" => ['nullable', 'boolean'],
        ];
    }
}
