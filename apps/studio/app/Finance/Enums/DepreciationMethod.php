<?php

namespace App\Finance\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DepreciationMethod: string
{
    case Gwg = 'gwg';
    case Linear = 'linear';

    public function label(): string
    {
        return match ($this) {
            self::Gwg => 'GWG',
            self::Linear => 'Linear',
        };
    }
}
