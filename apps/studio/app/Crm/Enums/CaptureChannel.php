<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the inquiry reached the studio — a different question from LeadSource,
 * which is how the client heard about it. A referral can arrive by phone or by
 * mail, and neither fact can be recovered afterwards, so both are stored.
 */
#[TypeScript]
enum CaptureChannel: string implements HasLabel
{
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Telefonisch',
            self::Email => 'Per E-Mail',
            self::InPerson => 'Persönlich',
            self::Other => 'Sonstige',
        };
    }
}
