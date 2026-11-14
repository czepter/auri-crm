<?php

use App\Payments\Actions\UpdatePaymentPlan;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentOverdue;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Jobs\ProcessStripeWebhookEvent;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Models\StripeEvent;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const M05_WEBHOOK_SECRET = 'whsec_test_m05secret';

beforeEach(function () {
    useSurface(Surface::API);
    config(['services.stripe.webhook_secret' => M05_WEBHOOK_SECRET]);
});

/** Stripe's own signing scheme — see stripe-php's WebhookSignature::verifyHeader(). */
function m05SignatureHeader(string $payload, string $secret = M05_WEBHOOK_SECRET, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}

/** @return array{0: string, 1: string} [json payload, Stripe-Signature header] */
function m05SignedEvent(array $event, string $secret = M05_WEBHOOK_SECRET): array
{
    $payload = json_encode($event);

    return [$payload, m05SignatureHeader($payload, $secret)];
}

/** @return array<string, mixed> a checkout.session.* event envelope, shaped like Stripe's own. */
function m05CheckoutSessionEvent(
    string $sessionId,
    string $type = 'checkout.session.completed',
    string $paymentStatus = 'paid',
    ?string $paymentIntent = 'pi_test_456',
    ?string $eventId = null,
    int $amountTotal = 45000,
): array {
    return [
        'id' => $eventId ?? 'evt_test_'.$sessionId.'_'.$type,
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'mode' => 'payment',
                'payment_status' => $paymentStatus,
                'payment_intent' => $paymentIntent,
                'amount_total' => $amountTotal,
            ],
        ],
    ];
}

function m05Installment(string $sessionId = 'cs_test_123', array $attributes = [], int $amount = 45000): PaymentInstallment
{
    // The plan's own sum-matches-total trigger (M-03) needs the plan total to
    // equal this one installment's amount.
    $plan = PaymentPlan::factory()->create(['total' => $amount]);

    return PaymentInstallment::factory()->for($plan, 'plan')->create([
        'stripe_checkout_session_id' => $sessionId,
        'amount' => $amount,
        ...$attributes,
    ]);
}

function m05Post(string $payload, string $signature)
{
    return test()->call('POST', '/api/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

// --- Signature verification --------------------------------------------------

it('rejects a bad signature without creating a stripe_events row or touching the database', function () {
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent($installment->stripe_checkout_session_id);
    [$payload] = m05SignedEvent($event);

    $response = m05Post($payload, m05SignatureHeader($payload, 'whsec_wrong_secret'));

    $response->assertStatus(400);
    expect(StripeEvent::query()->count())->toBe(0)
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Scheduled);
});

it('rejects a tampered payload signed for a different body, without touching the database', function () {
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent($installment->stripe_checkout_session_id);
    [$payload, $signature] = m05SignedEvent($event);

    $tampered = str_replace('"paid"', '"unpaid"', $payload);
    $response = m05Post($tampered, $signature);

    $response->assertStatus(400);
    expect(StripeEvent::query()->count())->toBe(0);
});

it('rejects a request with no Stripe-Signature header at all', function () {
    $event = m05CheckoutSessionEvent('cs_test_none');
    $payload = json_encode($event);

    $response = test()->call('POST', '/api/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(400);
    expect(StripeEvent::query()->count())->toBe(0);
});

it('rejects every request while no webhook secret is configured, even one signed with the empty key', function () {
    config(['services.stripe.webhook_secret' => null]);
    $installment = m05Installment();
    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent($installment->stripe_checkout_session_id), '');

    m05Post($payload, $signature)->assertStatus(400);

    expect(StripeEvent::query()->count())->toBe(0)
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Scheduled);
});

// --- Acknowledge-before-process ----------------------------------------------

it('acknowledges with 2xx and dispatches the processing job onto the default queue, without running it inline', function () {
    Queue::fake();
    $installment = m05Installment();
    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent($installment->stripe_checkout_session_id));

    $response = m05Post($payload, $signature);

    $response->assertSuccessful();
    expect($response->status())->toBeLessThan(300);

    Queue::assertPushedOn('default', ProcessStripeWebhookEvent::class);

    // The whole point: the response above returned before the job — which
    // Queue::fake() never actually ran — could have marked anything paid.
    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Scheduled);
});

it('records the event in stripe_events on first delivery', function () {
    Queue::fake();
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent($installment->stripe_checkout_session_id, eventId: 'evt_first_delivery');
    [$payload, $signature] = m05SignedEvent($event);

    m05Post($payload, $signature);

    $row = StripeEvent::query()->sole();
    expect($row->stripe_event_id)->toBe('evt_first_delivery')
        ->and($row->type)->toBe('checkout.session.completed');
});

// --- Idempotency: the redelivery case -----------------------------------------

it('replaying the same event produces exactly one state change and one InstallmentPaid, however many times it arrives', function () {
    Event::fake([InstallmentPaid::class]);
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent($installment->stripe_checkout_session_id, eventId: 'evt_replayed');
    [$payload, $signature] = m05SignedEvent($event);

    $first = m05Post($payload, $signature);
    $second = m05Post($payload, $signature);

    $first->assertSuccessful();
    $second->assertSuccessful();

    expect(StripeEvent::query()->count())->toBe(1)
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid);

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('never dispatches the processing job a second time for a duplicate delivery', function () {
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent($installment->stripe_checkout_session_id, eventId: 'evt_dup_job');
    [$payload, $signature] = m05SignedEvent($event);

    m05Post($payload, $signature);

    Queue::fake();
    $response = m05Post($payload, $signature);

    $response->assertSuccessful();
    Queue::assertNotPushed(ProcessStripeWebhookEvent::class);
});

// --- checkout.session.completed / async_payment_succeeded → InstallmentPaid --

it('marks the installment paid via Stripe, with the payment intent as the reference, once the job runs', function () {
    Event::fake([InstallmentPaid::class]);
    $installment = m05Installment();
    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent($installment->stripe_checkout_session_id));

    m05Post($payload, $signature)->assertSuccessful();

    $fresh = $installment->fresh();
    expect($fresh->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($fresh->settled_via->value)->toBe('stripe')
        ->and($fresh->payment_reference)->toBe('pi_test_456');

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('marks the installment paid on async_payment_succeeded, the delayed-payment-method completion event', function () {
    $installment = m05Installment();
    [$payload, $signature] = m05SignedEvent(
        m05CheckoutSessionEvent($installment->stripe_checkout_session_id, type: 'checkout.session.async_payment_succeeded')
    );

    m05Post($payload, $signature)->assertSuccessful();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid);
});

it('books the collected amount_total, so a payment for an old amount leaves a raised installment partial', function () {
    $installment = m05Installment(amount: 50000);

    app(UpdatePaymentPlan::class)($installment->plan, 80000, [
        ['public_id' => $installment->public_id, 'amount' => 80000, 'due_on' => $installment->due_on->toDateString()],
    ]);

    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent('cs_test_123', amountTotal: 50000));
    m05Post($payload, $signature)->assertSuccessful();

    $fresh = $installment->fresh();
    expect($fresh->status)->toBe(PaymentInstallmentStatus::Partial)
        ->and($fresh->paid_amount)->toBe(50000);
});

it('books only amount_total when it is lower than the installment', function () {
    $installment = m05Installment(amount: 80000);
    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent('cs_test_123', amountTotal: 50000));

    m05Post($payload, $signature)->assertSuccessful();

    $fresh = $installment->fresh();
    expect($fresh->status)->toBe(PaymentInstallmentStatus::Partial)
        ->and($fresh->paid_amount)->toBe(50000);
});

it('does not settle a cancelled installment and reports the received payment', function () {
    Exceptions::fake();
    $installment = m05Installment(attributes: ['status' => PaymentInstallmentStatus::Cancelled, 'cancelled_at' => now()]);
    [$payload, $signature] = m05SignedEvent(m05CheckoutSessionEvent('cs_test_123'));

    m05Post($payload, $signature)->assertSuccessful();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Cancelled)
        ->and($installment->fresh()->paid_amount)->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), $installment->public_id)
        && str_contains($e->getMessage(), 'cs_test_123')
        && str_contains($e->getMessage(), '45000'));
});

it('reports and skips a paid session without amount_total', function () {
    Exceptions::fake();
    $installment = m05Installment();
    $event = m05CheckoutSessionEvent('cs_test_123');
    unset($event['data']['object']['amount_total']);
    [$payload, $signature] = m05SignedEvent($event);

    m05Post($payload, $signature)->assertSuccessful();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Scheduled)
        ->and($installment->fresh()->paid_amount)->toBe(0);
    Exceptions::assertReported(RuntimeException::class);
});

it('leaves the installment untouched on checkout.session.completed when payment_status is not yet paid', function () {
    $installment = m05Installment();
    [$payload, $signature] = m05SignedEvent(
        m05CheckoutSessionEvent($installment->stripe_checkout_session_id, paymentStatus: 'unpaid')
    );

    m05Post($payload, $signature)->assertSuccessful();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Scheduled);
});

it('fails a paid event whose session id matches no installment, so it can be retried', function (string $type) {
    $job = new ProcessStripeWebhookEvent(m05CheckoutSessionEvent('cs_unknown', type: $type));

    app()->call([$job, 'handle']);
})->with([
    'checkout.session.completed',
    'checkout.session.async_payment_succeeded',
    'checkout.session.async_payment_failed',
])->throws(RuntimeException::class);

it('ignores an unhandled event type whose session id matches no installment', function () {
    $job = new ProcessStripeWebhookEvent(m05CheckoutSessionEvent('cs_unknown', type: 'charge.refunded'));

    app()->call([$job, 'handle']);

    expect(StripeEvent::query()->count())->toBe(0);
});

it('declares five tries with a growing backoff', function () {
    $job = new ProcessStripeWebhookEvent([]);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([10, 60, 300, 900]);
});

it('acknowledges an event type it does not handle, recording it but changing nothing', function () {
    [$payload, $signature] = m05SignedEvent([
        'id' => 'evt_unhandled',
        'object' => 'event',
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_test_1']],
    ]);

    $response = m05Post($payload, $signature);

    $response->assertSuccessful();
    expect(StripeEvent::query()->where('stripe_event_id', 'evt_unhandled')->exists())->toBeTrue();
});

// --- checkout.session.async_payment_failed → InstallmentOverdue --------------

it('dispatches InstallmentOverdue on async_payment_failed, via the same guarded path the daily scan uses', function () {
    Event::fake([InstallmentOverdue::class]);
    $installment = m05Installment(attributes: ['due_on' => now()->addDays(5)->toDateString()]);
    [$payload, $signature] = m05SignedEvent(
        m05CheckoutSessionEvent($installment->stripe_checkout_session_id, type: 'checkout.session.async_payment_failed', paymentStatus: 'unpaid', paymentIntent: null)
    );

    m05Post($payload, $signature)->assertSuccessful();

    $fresh = $installment->fresh();
    expect($fresh->overdue_notified_at)->not->toBeNull()
        ->and($fresh->status)->toBe(PaymentInstallmentStatus::Scheduled);

    Event::assertDispatchedTimes(InstallmentOverdue::class, 1);
});

it('never re-flags an installment already marked overdue by the daily scan', function () {
    Event::fake([InstallmentOverdue::class]);
    $installment = m05Installment(attributes: ['overdue_notified_at' => now()->subDay()]);
    [$payload, $signature] = m05SignedEvent(
        m05CheckoutSessionEvent($installment->stripe_checkout_session_id, type: 'checkout.session.async_payment_failed', paymentStatus: 'unpaid', paymentIntent: null)
    );

    m05Post($payload, $signature)->assertSuccessful();

    Event::assertNotDispatched(InstallmentOverdue::class);
});

it('never flags an already-paid installment overdue', function () {
    Event::fake([InstallmentOverdue::class]);
    $installment = m05Installment(attributes: [
        'status' => PaymentInstallmentStatus::Paid,
        'settled_via' => SettledVia::Transfer,
        'paid_on' => now()->toDateString(),
    ]);
    [$payload, $signature] = m05SignedEvent(
        m05CheckoutSessionEvent($installment->stripe_checkout_session_id, type: 'checkout.session.async_payment_failed', paymentStatus: 'unpaid', paymentIntent: null)
    );

    m05Post($payload, $signature)->assertSuccessful();

    Event::assertNotDispatched(InstallmentOverdue::class);
});

// --- Surface isolation ---------------------------------------------------------

it('is only reachable on the api surface', function () {
    $urisFor = fn (string $surface) => collect(routesForSurface($surface))->map(fn ($route) => $route->uri())->all();

    expect($urisFor(Surface::API))->toContain('api/stripe/webhook')
        ->and($urisFor(Surface::ADMIN))->not->toContain('api/stripe/webhook')
        ->and($urisFor(Surface::PORTAL))->not->toContain('api/stripe/webhook');
});
