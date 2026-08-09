<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a project came to be booked. Manual is a permanent path, not scaffolding
 * for P-05b: the studio books on a phone call, takes deposits by bank transfer,
 * and X-01 imports historical jobs that never had a contract row.
 *
 * P-05b adds no column — it writes the second value.
 */
#[TypeScript]
enum BookedVia: string implements HasLabel
{
    case Manual = 'manual';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuell',
            self::Contract => 'Vertrag',
        };
    }
}
