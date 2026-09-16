<?php

namespace App\Numbering\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Every kind of document a Nummernkreis (T-04) can number. Quote was the
 * first one anything allocated (Q-05/Q-06); Invoice existed here before
 * M-04's allocator landed, so it had a range already configured and seeded
 * to read from.
 *
 * CreditNote is its own range because § 14 Abs. 4 Nr. 4 UStG wants one
 * unbroken sequence per kind of document: a Gutschrift drawn from the
 * invoice range would leave a hole in it.
 */
#[TypeScript]
enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Angebot',
            self::Invoice => 'Rechnung',
            self::CreditNote => 'Gutschrift',
        };
    }
}
