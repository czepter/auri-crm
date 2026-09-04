<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a template's day_offset counts from. Lead stages have no shoot date to
 * count against — a lead is months of correspondence before a day exists — so
 * a template that fires there anchors on the day the stage was entered.
 *
 * Project stages can use either: "Ablaufplan abstimmen" is fourteen days before
 * the wedding, "Crew anfragen" two days after the booking landed.
 */
#[TypeScript]
enum DueAnchor: string implements HasLabel
{
    case StageEntry = 'stage';
    case ShootDate = 'shoot';

    public function label(): string
    {
        return match ($this) {
            self::StageEntry => 'Phasenwechsel',
            self::ShootDate => 'Termin',
        };
    }
}
