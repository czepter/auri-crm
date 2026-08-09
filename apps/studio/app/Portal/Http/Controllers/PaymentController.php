<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Actions\ConfirmInstallmentCheckoutSession;
use App\Payments\Data\PaymentPlanData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-07's portal payment screen — no mockup was drawn for this one ("Mockup —"
 * in the issue header), so this matches the closest existing portal screen
 * instead: App\Portal\Http\Controllers\ContractController's own shape
 * (contact/showSwitcher/project header, a 404 for a project the contact
 * cannot see, never a 403).
 *
 * "Überweisen" always renders — the studio's bank details (T-02) and the
 * plan's own reference (T-04's numbering, PaymentPlanData::$reference) —
 * regardless of whether Stripe is on. "Jetzt zahlen" is gated on
 * StudioSettingsResolver::stripeEnabled() alone. Nothing here ever CREATES a
 * Stripe object — that is App\Payments\Actions\CreateInstallmentCheckoutSession's
 * job, called only from the client's own click on PaymentCheckoutController.
 *
 * M-04: this is also where the client lands back from Stripe's hosted
 * Checkout page. A `session_id` query param (Stripe's own {CHECKOUT_SESSION_ID}
 * substitution, see PaymentCheckoutController) is what triggers the
 * provisional confirm step — see App\Payments\Actions\
 * ConfirmInstallmentCheckoutSession for what is provisional about it and what
 * M-05's webhook is expected to own instead. An ordinary visit with no
 * `session_id` never touches Stripe at all.
 */
class PaymentController extends Controller
{
    public function __invoke(
        Request $request,
        string $project,
        StudioSettingsResolver $settings,
        ConfirmInstallmentCheckoutSession $confirm,
    ): Response {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments')
            ->firstOrFail();

        $plan = $found->job?->paymentPlan;

        $sessionId = $request->query('session_id');
        if ($plan !== null && is_string($sessionId) && $settings->stripeEnabled()) {
            $installment = $plan->installments->firstWhere('stripe_checkout_session_id', $sessionId);

            if ($installment !== null) {
                $confirm($installment, $sessionId);
                $plan = $plan->fresh();
            }
        }

        $bank = $settings->attributes();
        $status = $request->query('status');

        return Inertia::render('Payments/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'plan' => $plan === null ? null : PaymentPlanData::fromModel($plan->load('installments')),
            'stripeEnabled' => $settings->stripeEnabled(),
            'paymentStatus' => in_array($status, ['success', 'cancelled'], true) ? $status : null,
            'bankDetails' => [
                'accountHolder' => $bank['bank_account_holder'],
                'iban' => $bank['bank_iban'],
                'bic' => $bank['bank_bic'],
            ],
        ]);
    }
}
