<?php

namespace App\Quotes\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Either shape a line or a quote discount can be entered as, before
 * App\Quotes\Support\DiscountCalculator resolves it to a minor-unit amount.
 * Percentage is stored as basis points (0–10000, i.e. two decimal places of
 * percent) so the resolver only ever divides by one fixed number and never
 * touches a float.
 */
#[TypeScript]
enum DiscountType: string
{
    case Percentage = 'percentage';
    case Amount = 'amount';
}
