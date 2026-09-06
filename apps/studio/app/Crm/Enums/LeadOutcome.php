<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a pursuit ended. `lost_at` carried only "not booked" and left the why
 * in free text; T-20's scan needs to tell a finished inquiry from a waiting
 * one, and a client who cancelled from one the studio turned away.
 *
 * Null is open, and open is the only state the stalled scan reminds about.
 */
#[TypeScript]
enum LeadOutcome: string implements HasLabel
{
    case Lost = 'lost';
    case Declined = 'declined';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Lost => 'Verloren',
            self::Declined => 'Abgelehnt',
            self::Closed => 'Geschlossen',
        };
    }
}
