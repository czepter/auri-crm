<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Data\PaymentPlanData;
use App\Settings\Support\StudioSettingsResolver;
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
 * StudioSettingsResolver::stripeEnabled() alone; nothing here ever creates a
 * Stripe object; that is M-04/M-05's action, called from the client's own
 * click, per the issue's own "nothing is created in Stripe until the client
 * clicks pay".
 */
class PaymentController extends Controller
{
    public function __invoke(string $project, StudioSettingsResolver $settings): Response
    {
        $contact = auth('portal')->user();

        $found = $contact->visibleProjects()
            ->where('projects.public_id', $project)
            ->with('job.paymentPlan.installments')
            ->firstOrFail();

        $plan = $found->job?->paymentPlan;
        $bank = $settings->attributes();

        return Inertia::render('Payments/Show', [
            'contact' => ['name' => $contact->name],
            'showSwitcher' => $contact->visibleProjects()->count() > 1,
            'project' => ['id' => $found->public_id, 'name' => $found->name],
            'plan' => $plan === null ? null : PaymentPlanData::fromModel($plan->load('installments')),
            'stripeEnabled' => $settings->stripeEnabled(),
            'bankDetails' => [
                'accountHolder' => $bank['bank_account_holder'],
                'iban' => $bank['bank_iban'],
                'bic' => $bank['bank_bic'],
            ],
        ]);
    }
}
