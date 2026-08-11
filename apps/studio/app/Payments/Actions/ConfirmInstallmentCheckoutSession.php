<?php

namespace App\Payments\Actions;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use Stripe\StripeClient;

/**
 * The provisional confirmation path T-07's own issue left room for: "if you
 * can reasonably call MarkInstallmentPaid synchronously ... that's a
 * reasonable interim approach". Called only from the client's own return
 * trip from Stripe's hosted Checkout page — App\Portal\Http\Controllers\
 * PaymentController, never a webhook.
 *
 * M-05 shipped the durable counterpart this class's own comment used to
 * point at: App\Payments\Http\Controllers\StripeWebhookController, verified
 * against Stripe-Signature and deduplicated by event ID in stripe_events,
 * processed on the queue. That path is now the authoritative one — it fires
 * whether or not the client's browser ever makes the return trip at all
 * (closed tab, crashed device, a delayed payment method that clears after
 * the redirect already happened). This class stays, deliberately, as a
 * same-page UX speedup for the ordinary case: without it, a client who paid
 * and landed straight back on this screen would see it still say "unpaid"
 * until the webhook — usually seconds away, but not instant — catches up.
 * Both call the exact same App\Payments\Actions\MarkInstallmentPaid, whose
 * own paid-guard is what makes calling it from both places safe: whichever
 * of the two arrives first wins, and the other is a no-op.
 *
 * Re-verifies the session with Stripe's own retrieve API rather than trusting
 * the `session_id` query string alone, and only for an installment whose OWN
 * stored stripe_checkout_session_id already matches it — a query string built
 * by hand can name a real session, but never one that was not this
 * installment's, so it can never mark a different installment (or a
 * different tenant's) paid.
 *
 * Calls the SAME App\Payments\Actions\MarkInstallmentPaid a staff member's
 * manual "mark paid" uses, so a synchronous Stripe payment unlocks exactly
 * what a bank transfer would (T-07's own acceptance criterion) — and inherits
 * that action's idempotency guard for free, so confirming twice (a page
 * reload on the success screen, say) writes nothing a second time.
 */
class ConfirmInstallmentCheckoutSession
{
    public function __construct(
        private StudioSettingsResolver $settings,
        private MarkInstallmentPaid $markPaid,
    ) {}

    public function __invoke(PaymentInstallment $installment, string $sessionId): PaymentInstallment
    {
        if ($installment->stripe_checkout_session_id !== $sessionId) {
            return $installment;
        }

        if ($installment->status === PaymentInstallmentStatus::Paid) {
            return $installment;
        }

        $client = new StripeClient($this->settings->activeStripeSecretKey());
        $session = $client->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return $installment;
        }

        $paymentIntent = $session->payment_intent;

        return ($this->markPaid)(
            $installment,
            SettledVia::Stripe,
            now('UTC')->toDateString(),
            is_string($paymentIntent) ? $paymentIntent : null,
        );
    }
}
