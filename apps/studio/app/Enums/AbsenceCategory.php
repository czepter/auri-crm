<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What kind of time away this is — never why. A closed set instead of a
 * reason field is T-10's whole DSGVO story: "sick" is health data under
 * Art. 9, and a free-text reason is how a CRM quietly acquires a
 * special-category obligation nobody meant to take on.
 */
#[TypeScript]
enum AbsenceCategory: string
{
    case Unavailable = 'unavailable';
    case Holiday = 'holiday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Unavailable => 'Nicht verfügbar',
            self::Holiday => 'Urlaub',
            self::Other => 'Sonstiges',
        };
    }
}
