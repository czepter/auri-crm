<?php

namespace App\Payments\Events;

use App\Payments\Models\PaymentInstallment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised by the scheduled scan (App\Payments\Actions\
 * DetectOverdueInstallments), never by a Stripe signal alone — T-07's own
 * issue asks for a provider-independent due-date scan with any Stripe
 * signal as an ADDITIONAL input, not the only one, so a transfer-only
 * studio's overdue installments are found exactly as reliably as a
 * Stripe-enabled one's. A future failed-payment webhook (I-04) is expected
 * to dispatch this same event, not a Stripe-only sibling of it.
 *
 * Fires at most once per installment — payment_installments.overdue_notified_at
 * is the guard, the same one-way idempotency shape InstallmentPaid's own
 * dispatch site (MarkInstallmentPaid) uses.
 */
class InstallmentOverdue
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentInstallment $installment,
    ) {}
}
