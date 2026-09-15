<?php

namespace App\Payments\Events;

use App\Payments\Models\PaymentInstallment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The anchor T-07's own issue names three future listeners against: G-05
 * gates galleries on a settled balance, E-07 fans out from it, and I-04
 * registers webhooks on it. Fires for a bank transfer marked paid by staff
 * exactly the same as it will for a real Stripe payment once M-04/M-05
 * land — carrying the PaymentInstallment is what lets every listener reach
 * ->settled_via, ->plan and ->plan->job without re-querying, the same shape
 * App\Crm\Events\ProjectBooked already carries its own subject in.
 *
 * Fires at most once per installment: App\Payments\Actions\
 * MarkInstallmentPaid guards a second "mark paid", so a doubled request never dispatches this
 * twice.
 */
class InstallmentPaid
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentInstallment $installment,
    ) {}
}
