<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How the client heard about the studio. A fixed list rather than free text:
 * it is the one fact that cannot be read back out of the inquiry text later,
 * and its entire value is being countable (mockup 03).
 */
#[TypeScript]
enum LeadSource: string implements HasLabel
{
    case Referral = 'referral';
    case Website = 'website';
    case Instagram = 'instagram';
    case WeddingFair = 'wedding_fair';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Empfehlung',
            self::Website => 'Website',
            self::Instagram => 'Instagram',
            self::WeddingFair => 'Hochzeitsmesse',
            self::Other => 'Sonstige',
        };
    }
}
