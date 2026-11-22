<?php

namespace App\Finance\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Booked = 'booked';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Booked => 'Gebucht',
            self::Ignored => 'Verworfen',
        };
    }
}
