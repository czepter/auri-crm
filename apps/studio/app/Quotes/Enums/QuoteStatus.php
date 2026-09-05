<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The full lifecycle a quote can end up in, declared whole even though this
 * story only ever reaches Draft. Sent is Q-06's transition; Accepted and
 * Superseded are Q-07's; Expired is a future sweep over valid_until. No
 * transition logic lives here — declaring the shape now is what lets those
 * stories add behaviour without widening this column's domain later.
 */
#[TypeScript]
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::Accepted => 'Angenommen',
            self::Superseded => 'Ersetzt',
            self::Expired => 'Abgelaufen',
        };
    }
}
