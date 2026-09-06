<?php

namespace App\Payments\Jobs;

use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\OverdueReason;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * M-05: the actual work behind a verified, deduplicated Stripe event —
 * dispatched by App\Payments\Http\Controllers\StripeWebhookController
 * AFTER it has already recorded the event and returned 2xx, so nothing
 * this job does (a slow database, a lock wait) can ever be why Stripe
 * thinks the delivery failed and retries it.
 *
 * The event array, not a live \Stripe\Event object — a queued job is
 * serialized and re-hydrated on a worker process (App\Jobs\PingJob's own
 * README in config/horizon.php), and this job's whole input is already
 * exactly what the webhook delivered, so there is nothing left to fetch
 * back from Stripe. checkout.session.completed's own payload carries the
 * session's payment_status and payment_intent directly.
 *
 * Event-type mapping (see the PR description for the fuller version): M-04
 * built the "Jetzt zahlen" flow on Checkout Sessions, not Invoices, so the
 * events Stripe actually sends for it are Checkout Session events, not the
 * issue's literal invoice.paid / invoice.payment_failed wording (which
 * predates M-04's design):
 *   - checkout.session.completed with payment_status "paid", and
 *     checkout.session.async_payment_succeeded (a delayed method, e.g.
 *     SEPA debit, clearing after the redirect) → InstallmentPaid, via T-07's
 *     own MarkInstallmentPaid, settled_via = stripe.
 *   - checkout.session.async_payment_failed → InstallmentOverdue, via the
 *     SAME guarded path T-07's DetectOverdueInstallments scan already uses
 *     (its markOverdue()), not a new emission built for this alone.
 * Every other event type this endpoint might receive (Stripe sends whatever
 * the account is subscribed to) is a deliberate no-op: recorded in
 * stripe_events for idempotency, acknowledged, nothing else.
 *
 * Resolves the installment by matching the Checkout Session's own id
 * against payment_installments.stripe_checkout_session_id — the same
 * column, and the same match, App\Payments\Actions\
 * ConfirmInstallmentCheckoutSession already keys its own confirmation on.
 * That column lives on a tenant-scoped model (App\Concerns\BelongsToTenant),
 * so the moment this job resolves one, the tenant boundary the query
 * already enforces is the only one this event ever needed — no metadata on
 * the Checkout Session (M-04 never sets any) is read or required for this.
 */
class ProcessStripeWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $event */
    public function __construct(public readonly array $event) {}

    public function handle(MarkInstallmentPaid $markPaid, DetectOverdueInstallments $overdue): void
    {
        $type = $this->event['type'] ?? null;
        $session = $this->event['data']['object'] ?? [];
        $sessionId = $session['id'] ?? null;

        if (! is_string($sessionId)) {
            return;
        }

        $installment = PaymentInstallment::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();

        if ($installment === null) {
            return;
        }

        match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handlePaid($installment, $session, $markPaid),
            'checkout.session.async_payment_failed' => $overdue->markOverdue($installment, OverdueReason::PaymentFailed),
            default => null,
        };
    }

    /** @param array<string, mixed> $session */
    private function handlePaid(PaymentInstallment $installment, array $session, MarkInstallmentPaid $markPaid): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            // completed with an async method still in flight — its own
            // async_payment_succeeded event is what actually settles this.
            return;
        }

        $paymentIntent = $session['payment_intent'] ?? null;

        $markPaid(
            $installment,
            SettledVia::Stripe,
            now('UTC')->toDateString(),
            is_string($paymentIntent) ? $paymentIntent : null,
        );
    }
}
