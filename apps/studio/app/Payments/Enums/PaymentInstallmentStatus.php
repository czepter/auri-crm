<?php

namespace App\Payments\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * scheduled -> invoiced -> paid, though the middle step is optional: a
 * transfer that arrives before the paperwork goes straight to paid, and
 * M-06's IssueInvoice leaves an already-paid row paid rather than walking it
 * backwards.
 *
 * M-03 declared `invoiced` before anything produced it — the same shape
 * App\Contracts\Enums\ContractStatus used for `failed` — so that M-04 would
 * have somewhere to land without a migration widening this column's domain.
 * M-04 shipped as Stripe Checkout and never used it; M-06's own invoice is
 * what finally writes it, which is also what makes PaymentPlan::isEditable()'s
 * defensive guard load-bearing rather than hypothetical.
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
