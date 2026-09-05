<?php

namespace App\Numbering\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Every kind of document a Nummernkreis (T-04) can number. Quote is the only
 * one anything allocates today (Q-05/Q-06); Contract and Invoice exist here
 * so M-01's and M-04's own allocators have a range already configured and
 * seeded to read from, rather than a migration of their own the day they
 * land.
 */
#[TypeScript]
enum DocumentType: string
{
    case Quote = 'quote';
    case Contract = 'contract';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Angebot',
            self::Contract => 'Vertrag',
            self::Invoice => 'Rechnung',
        };
    }
}
