<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * C-25: what a studio may ask of a project of one type. Deliberately not
 * FormFieldType — the questionnaire knows files, name lists and group photos,
 * a project field does not, and the two vocabularies are meant to grow apart.
 */
#[TypeScript]
enum ProjectFieldType: string implements HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Tags = 'tags';
    case Date = 'date';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Mehrzeilig',
            self::Number => 'Zahl',
            self::Select => 'Auswahl',
            self::Tags => 'Tags',
            self::Date => 'Datum',
            self::Boolean => 'Ja/Nein',
        };
    }

    /** A tag is a multi-valued selection, so both read ProjectField.options. */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::Tags;
    }

    /** @return list<string> */
    public function valueRules(): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Textarea => ['string', 'max:5000'],
            self::Number => ['numeric'],
            self::Select => ['string', 'max:255'],
            self::Tags => ['array'],
            self::Date => ['date_format:Y-m-d'],
            self::Boolean => ['boolean'],
        };
    }
}
