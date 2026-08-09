<?php

namespace App\Crm\Enums;

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
        };
    }

    /** Only "select" reads FormField.options — everything else leaves it null. */
    public function hasOptions(): bool
    {
        return $this === self::Select;
    }
}
