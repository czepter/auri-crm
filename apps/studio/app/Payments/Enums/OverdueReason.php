<?php

namespace App\Payments\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-22: why an installment counts as overdue. markOverdue() has two callers
 * and they mean different things — a passed due date is silence, a declined
 * card is a client who tried. Somebody reading "überfällig" about the second
 * one calls to ask for money that is already on its way.
 */
#[TypeScript]
enum OverdueReason: string
{
    case DueDatePassed = 'due_date_passed';
    case PaymentFailed = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::DueDatePassed => 'Frist überschritten',
            self::PaymentFailed => 'Zahlung fehlgeschlagen',
        };
    }
}
