<?php

namespace App\Payments\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * scheduled -> paid is the whole lifecycle this story ever writes.
 *
 * Invoiced exists as a column value now because M-04's real Stripe invoicing
 * needs somewhere to land without a later migration widening this column's
 * domain — the same reason App\Contracts\Enums\ContractStatus declared
 * `failed` before anything produced it. Nothing in this story ever sets it;
 * PaymentPlan::isEditable() is the guard written defensively against the day
 * something does.
 */
#[TypeScript]
enum PaymentInstallmentStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Geplant',
            self::Paid => 'Bezahlt',
            self::Invoiced => 'Gestellt',
        };
    }
}
