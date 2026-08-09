<?php

namespace App\Numbering\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a range's counter behaves at a year boundary. Yearly is Q-05's
 * original, only behaviour: AN-2026-1, back to AN-2027-1 on January 1st.
 * Continuous never resets — the German Nummernkreis some studios run for an
 * invoice sequence that has to keep climbing across years to stay gapless in
 * a single, uninterrupted line.
 */
#[TypeScript]
enum NumberRangeResetRule: string
{
    case Yearly = 'yearly';
    case Continuous = 'continuous';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => 'Jährlich',
            self::Continuous => 'Fortlaufend',
        };
    }
}
