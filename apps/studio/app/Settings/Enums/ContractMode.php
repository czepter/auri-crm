<?php

namespace App\Settings\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractMode: string
{
    case Always = 'always';
    case OnDemand = 'on_demand';
    case Off = 'off';
}
