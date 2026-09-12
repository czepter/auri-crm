<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PricingMode: string
{
    case Classic = 'classic';
    case Choice = 'choice';
}
