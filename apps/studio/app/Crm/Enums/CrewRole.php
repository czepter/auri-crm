<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What someone does on the day, which is not what they are employed as.
 *
 * Deliberately not StaffRole. That enum carries Editor, and an editor never
 * attends a shoot — reusing it would let an assignment row express something
 * meaningless. Keeping them apart also keeps S-06 honest: "a second shooter
 * sees the shoots they are assigned to" becomes a policy over CrewAssignment
 * rather than a coincidence of two enums sharing a word.
 */
#[TypeScript]
enum CrewRole: string implements HasLabel
{
    case LeadPhotographer = 'lead_photographer';
    case SecondShooter = 'second_shooter';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::LeadPhotographer => 'Hauptfotograf',
            self::SecondShooter => 'Zweitfotograf',
            self::Assistant => 'Assistenz',
        };
    }
}
