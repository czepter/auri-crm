<?php

namespace App\Payments\Actions;

use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\PaymentSettings;
use Stripe\StripeClient;

/**
 * The one place a Stripe object is ever created — never eagerly, only from
 * the client's own "Jetzt zahlen" click (T-07's amendment to this story:
 * "nothing is created in Stripe until the client clicks pay"). Called by
 * App\Portal\Http\Controllers\PaymentCheckoutController, never from a queued
 * job or a plan lifecycle hook.
 *
 * A Checkout Session, not an Invoice or a bare PaymentIntent. The acceptance
 * this button needs is "redirect the client to Stripe's hosted payment
 * page", which is exactly what a Checkout Session's `url` gives for free. An
 * Invoice is Stripe's own primitive for invoicing-and-waiting — a due date
 * and Stripe-hosted dunning, never a client-present redirect — which is the
 * opposite of a click-to-pay button, and T-07's own issue already flagged
 * the original "one invoice per installment" phrasing for revisiting here. A
 * bare PaymentIntent has no hosted page of its own; using one would mean
 * building and maintaining a Stripe Elements card form on this screen
 * instead of the one redirect a Checkout Session already provides.
 *
 * Idempotent twice over. The installment's own stripe_checkout_session_id
 * column is what a repeat click actually reuses — checked first, before any
 * Stripe call. Stripe's own idempotency key (the installment's public_id and
 * the payable, which #414's part payments move under it) only guards the
 * narrower case of two requests racing
 * before that column is written; unlike the column, it expires after 24
 * hours on Stripe's side, so it is a second line of defence, not the first.
 */
class CreateInstallmentCheckoutSession
{
    public function __invoke(PaymentInstallment $installment, string $successUrl, string $cancelUrl): string
    {
        $client = new StripeClient(PaymentSettings::activeStripeSecretKey());
        $payable = $installment->remaining();

        if ($installment->stripe_checkout_session_id !== null) {
            $existing = $client->checkout->sessions->retrieve($installment->stripe_checkout_session_id);

            // #414: a part payment booked since that session was opened moved
            // the payable, and a session's amount cannot be edited. Reusing it
            // would charge the old figure.
            if ($existing->status === 'open' && $existing->amount_total === $payable) {
                return $existing->url;
            }

            // ponytail: an expired or already-completed session falls
            // through to a fresh one below rather than a repair path — the
            // client's own return trip (ConfirmInstallmentCheckoutSession)
            // is what actually marks a completed session paid, so a single
            // fresh session per stale one is all a retry-by-click needs.
        }

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $payable,
                    'product_data' => ['name' => $this->description($installment)],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], [
            'idempotency_key' => "{$installment->public_id}:{$payable}",
        ]);

        $installment->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    /** T-04's own allocated document number, when the plan has one — the same Verwendungszweck "Überweisen" already prints. */
    private function description(PaymentInstallment $installment): string
    {
        $reference = $installment->plan->reference;

        return $reference === null ? 'Zahlung' : "Zahlung {$reference}";
    }
}
