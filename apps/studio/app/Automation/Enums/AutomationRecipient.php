<?php

namespace App\Automation\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * E-14: who a rule writes to.
 *
 * `crew` and `studio` are not one value, because StudioAudience::forProject()
 * widens to the whole studio where no crew is assigned. That fallback is
 * right for "Anfrage prüfen" and wrong for "Ausrüstung packen" — `crew` means
 * the assigned crew and stays silent without one.
 */
#[TypeScript]
enum AutomationRecipient: string implements HasLabel
{
    case Client = 'client';
    case Crew = 'crew';
    case Studio = 'studio';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Kunde',
            self::Crew => 'Crew',
            self::Studio => 'Studio',
        };
    }
}
