<?php

namespace App\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Actions\CreateInstallmentCheckoutSession;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * M-04: the client's own click, and the only place in the app that ever
 * creates a Stripe object — see App\Payments\Actions\
 * CreateInstallmentCheckoutSession. Asks the same "is this project visible
 * to this contact" question PaymentController and ContractController already
 * ask, so an installment belonging to a project the contact cannot see is a
 * 404 here exactly as it is absent from the payments screen — never a 403.
 *
 * Redirects with Inertia::location() rather than a plain redirect(): Stripe's
 * hosted Checkout page is off-origin, and an Inertia XHR visit would
 * otherwise try to swap it in as a page component instead of letting the
 * browser actually navigate there. On a plain (non-Inertia) request — what
 * the portal's own `router.post()` sends here — this resolves to an ordinary
 * redirect response.
 */
class PaymentCheckoutController extends Controller
{
    public function __invoke(
        string $project,
        PaymentInstallment $installment,
        StudioSettingsResolver $settings,
        CreateInstallmentCheckoutSession $createSession,
    ): Response {
        abort_unless($settings->stripeEnabled() && $settings->activeStripeSecretKey() !== null, 404);

        $contact = auth('portal')->user();
        $visible = $contact->visibleProjects()->where('projects.public_id', $project)->exists();
        abort_unless($visible, 404);
        abort_unless($installment->plan?->job?->project?->public_id === $project, 404);
        abort_if($installment->status === PaymentInstallmentStatus::Paid, 404);
        abort_if($installment->status === PaymentInstallmentStatus::Cancelled, 404);
        // #418: a settled plan takes no more money.
        abort_if($installment->plan->isSettled(), 404);

        // A literal path, not route(): App\Portal\Http\Controllers\
        // ContractController's own download link is built the same way — the
        // named-route lookup depends on whichever RouteCollection last
        // booted, which the admin/portal surface switch (App\Support\Surface)
        // does not always leave pointed at this surface's own table.
        $showUrl = url("/projekte/{$project}/zahlungen");

        $url = $createSession(
            $installment,
            // Stripe fills the placeholder with the session's own id on
            // success — the id ConfirmInstallmentCheckoutSession then
            // re-verifies against Stripe rather than trusting outright.
            $showUrl.'?status=success&session_id={CHECKOUT_SESSION_ID}',
            $showUrl.'?status=cancelled',
        );

        return Inertia::location($url);
    }
}
