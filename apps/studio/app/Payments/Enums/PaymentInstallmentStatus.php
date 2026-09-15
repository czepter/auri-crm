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
 * what finally writes it. Since #407 the status no longer decides what may
 * be edited — `invoiced_at` does, on the row itself.
 *
 * `cancelled` (#408) is terminal and reachable only from `invoiced`: the row
 * keeps its number and its PDF, and nothing that chases money — overdue
 * detection, the reminder ladders, Stripe checkout — looks at it again.
 */
#[TypeScript]
enum PaymentInstallmentStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Geplant',
            self::Paid => 'Bezahlt',
            self::Invoiced => 'Gestellt',
            self::Cancelled => 'Storniert',
        };
    }
}
