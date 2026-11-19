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
    case GroupPhotos = 'group_photos';
    case File = 'file';
    case Section = 'section';

    /** What a "Datei" field may be configured to accept. Nothing outside this list is storable. */
    public const FILE_FORMATS = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'webp', 'docx'];

    public const FILE_MAX_MB = 25;

    /** What a "Gruppenfotos" plan may grow to before it stops being a plan. */
    public const GROUP_MINUTES_MAX = 15;

    public const GROUP_PEOPLE_MAX = 100;

    public const GROUP_SHOTS_MAX = 60;

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
            self::GroupPhotos => 'Gruppenfotos',
            self::File => 'Datei',
            self::Section => 'Abschnitt',
        };
    }

    /** "select", "file" and "group_photos" read FormField.options — everything else leaves it null. */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::File || $this === self::GroupPhotos;
    }

    /**
     * Rules for one field's options, keyed by payload path. Three shapes
     * share the one json column: select's list of choices, file's upload
     * config, group_photos' builder configuration. Nullable throughout
     * because a field may arrive from a template that never configured it —
     * FormInstanceField::fileOptions() and ::groupPhotoOptions() fill the rest.
     *
     * @return array<string, list<mixed>>
     */
    public function optionRules(string $prefix): array
    {
        return match ($this) {
            self::File => [
                "{$prefix}.options" => ['nullable', 'array'],
                "{$prefix}.options.formats" => ['nullable', 'array', 'min:1'],
                "{$prefix}.options.formats.*" => ['string', Rule::in(self::FILE_FORMATS)],
                "{$prefix}.options.maxSize" => ['nullable', 'integer', 'min:1', 'max:'.self::FILE_MAX_MB],
                "{$prefix}.options.multiple" => ['nullable', 'boolean'],
            ],
            self::GroupPhotos => [
                "{$prefix}.options" => ['nullable', 'array'],
                "{$prefix}.options.suggestions" => ['nullable', 'array', 'max:'.self::GROUP_SHOTS_MAX],
                "{$prefix}.options.suggestions.*" => ['string', 'max:255'],
                "{$prefix}.options.minutesPerGroup" => ['nullable', 'integer', 'min:1', 'max:'.self::GROUP_MINUTES_MAX],
            ],
            self::Section => ["{$prefix}.options" => ['prohibited']],
            default => [
                "{$prefix}.options" => ['nullable', 'array'],
                "{$prefix}.options.*" => ['string', 'max:255'],
            ],
        };
    }
}
