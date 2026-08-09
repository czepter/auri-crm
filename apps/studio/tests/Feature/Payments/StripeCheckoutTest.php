<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Actions\ConfirmInstallmentCheckoutSession;
use App\Payments\Actions\CreateInstallmentCheckoutSession;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    // StudioSettingsResolver caches the settings row (T-01).
    Cache::store('redis')->flush();
    // ->first()->update(), not the query builder's mass update: the latter
    // bypasses the model's `encrypted` cast and writes the key columns as
    // plaintext, which then fails to decrypt the moment anything reads them.
    StudioSettings::query()->first()->update([
        'payments_stripe_enabled' => true,
        'payments_stripe_mode' => 'test',
        'payments_stripe_test_publishable_key' => 'pk_test_abc123',
        'payments_stripe_test_secret_key' => 'sk_test_abc123',
        // T-08's "see payment status" switch defaults to off and now also
        // gates the checkout route this suite exercises (routes/portal.php).
        'portal_payment_status_enabled' => true,
    ]);
});

// Global state on \Stripe\ApiRequestor — never leaks into a suite that never
// asked for it.
afterEach(fn () => ApiRequestor::setHttpClient(null));

/** @return array{status: int, body: array} */
function m04CheckoutSessionResponse(string $id = 'cs_test_123', string $status = 'open', string $paymentStatus = 'unpaid'): array
{
    return [
        'status' => 200,
        'body' => [
            'id' => $id,
            'object' => 'checkout.session',
            'url' => "https://checkout.stripe.com/c/pay/{$id}",
            'status' => $status,
            'payment_status' => $paymentStatus,
            'mode' => 'payment',
            'payment_intent' => 'pi_test_456',
        ],
    ];
}

/** @return array{0: Project, 1: Contact, 2: PaymentPlan, 3: PaymentInstallment} */
function m04PlanWithInstallment(int $amount = 45000): array
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => $amount, 'reference' => 'RE-2026-77']);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => $amount]);

    return [$project, $contact, $plan, $installment];
}

// --- App\Payments\Actions\CreateInstallmentCheckoutSession ------------------

it('creates exactly one Checkout Session, in minor units, with the installment\'s public_id as the Stripe idempotency key', function () {
    [, , , $installment] = m04PlanWithInstallment(45000);

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse()]);
    ApiRequestor::setHttpClient($fake);

    $url = app(CreateInstallmentCheckoutSession::class)($installment, 'https://portal.test/success', 'https://portal.test/cancel');

    expect($url)->toBe('https://checkout.stripe.com/c/pay/cs_test_123')
        ->and($installment->fresh()->stripe_checkout_session_id)->toBe('cs_test_123')
        ->and($fake->requests)->toHaveCount(1);

    $sent = $fake->requests[0];
    expect($sent['params']['line_items'][0]['price_data']['unit_amount'])->toBe(45000)
        ->and($sent['params']['line_items'][0]['price_data']['currency'])->toBe('eur')
        ->and($sent['params']['mode'])->toBe('payment')
        ->and($sent['params']['success_url'])->toBe('https://portal.test/success')
        ->and($sent['params']['cancel_url'])->toBe('https://portal.test/cancel')
        ->and($sent['headers'])->toContain('Idempotency-Key: '.$installment->public_id);
});

it('reuses the stored session on a repeat call while it is still open, rather than creating a second one', function () {
    [, , , $installment] = m04PlanWithInstallment();

    $fake = new FakeStripeHttpClient([
        m04CheckoutSessionResponse(),
        m04CheckoutSessionResponse(), // the retrieve the second call makes
    ]);
    ApiRequestor::setHttpClient($fake);

    $create = app(CreateInstallmentCheckoutSession::class);
    $first = $create($installment, 'https://portal.test/success', 'https://portal.test/cancel');
    $second = $create($installment->fresh(), 'https://portal.test/success', 'https://portal.test/cancel');

    expect($first)->toBe($second)
        ->and($fake->requests)->toHaveCount(2)
        ->and($fake->requests[0]['method'])->toBe('post')
        ->and($fake->requests[1]['method'])->toBe('get');
});

it('creates a fresh session when the stored one is no longer open', function () {
    [, , , $installment] = m04PlanWithInstallment();

    $fake = new FakeStripeHttpClient([
        m04CheckoutSessionResponse(id: 'cs_test_first'),
        m04CheckoutSessionResponse(id: 'cs_test_first', status: 'expired'),
        m04CheckoutSessionResponse(id: 'cs_test_second'),
    ]);
    ApiRequestor::setHttpClient($fake);

    $create = app(CreateInstallmentCheckoutSession::class);
    $create($installment, 'https://portal.test/success', 'https://portal.test/cancel');
    $second = $create($installment->fresh(), 'https://portal.test/success', 'https://portal.test/cancel');

    expect($second)->toBe('https://checkout.stripe.com/c/pay/cs_test_second')
        ->and($installment->fresh()->stripe_checkout_session_id)->toBe('cs_test_second');
});

// --- App\Payments\Actions\ConfirmInstallmentCheckoutSession -----------------

it('marks the installment paid via Stripe once the returning Checkout Session reports paid', function () {
    Event::fake([InstallmentPaid::class]);
    [, , , $installment] = m04PlanWithInstallment();
    $installment->update(['stripe_checkout_session_id' => 'cs_test_123']);

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse(paymentStatus: 'paid')]);
    ApiRequestor::setHttpClient($fake);

    $result = app(ConfirmInstallmentCheckoutSession::class)($installment, 'cs_test_123');

    expect($result->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($result->settled_via)->toBe(SettledVia::Stripe)
        ->and($result->payment_reference)->toBe('pi_test_456');

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('leaves the installment untouched when the session has not been paid yet', function () {
    [, , , $installment] = m04PlanWithInstallment();
    $installment->update(['stripe_checkout_session_id' => 'cs_test_123']);

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse(paymentStatus: 'unpaid')]);
    ApiRequestor::setHttpClient($fake);

    $result = app(ConfirmInstallmentCheckoutSession::class)($installment, 'cs_test_123');

    expect($result->status)->toBe(PaymentInstallmentStatus::Scheduled);
});

it('ignores a session id that does not match the installment\'s own stored session, without calling Stripe', function () {
    [, , , $installment] = m04PlanWithInstallment();
    $installment->update(['stripe_checkout_session_id' => 'cs_test_real']);

    $fake = new FakeStripeHttpClient([]);
    ApiRequestor::setHttpClient($fake);

    $result = app(ConfirmInstallmentCheckoutSession::class)($installment, 'cs_test_forged');

    expect($result->status)->toBe(PaymentInstallmentStatus::Scheduled)
        ->and($fake->requests)->toBeEmpty();
});

it('is idempotent: confirming an already-paid installment calls Stripe nothing new and dispatches nothing', function () {
    Event::fake([InstallmentPaid::class]);
    [, , , $installment] = m04PlanWithInstallment();
    $installment->update([
        'stripe_checkout_session_id' => 'cs_test_123',
        'status' => PaymentInstallmentStatus::Paid,
        'settled_via' => SettledVia::Stripe,
        'paid_on' => '2026-08-01',
    ]);

    $fake = new FakeStripeHttpClient([]);
    ApiRequestor::setHttpClient($fake);

    app(ConfirmInstallmentCheckoutSession::class)($installment, 'cs_test_123');

    expect($fake->requests)->toBeEmpty();
    Event::assertNotDispatched(InstallmentPaid::class);
});

// --- The portal HTTP boundary -----------------------------------------------

it('starts a Checkout Session from the client\'s own click and redirects to Stripe\'s hosted page', function () {
    [$project, $contact, , $installment] = m04PlanWithInstallment(45000);

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse()]);
    ApiRequestor::setHttpClient($fake);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen")
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    expect($installment->fresh()->stripe_checkout_session_id)->toBe('cs_test_123');
});

it('reuses the same Checkout Session on a second click for the same still-unpaid installment', function () {
    [$project, $contact, , $installment] = m04PlanWithInstallment();

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse(), m04CheckoutSessionResponse()]);
    ApiRequestor::setHttpClient($fake);

    $this->actingAs($contact, 'portal')->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen");
    $this->actingAs($contact, 'portal')->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen");

    $creates = collect($fake->requests)->where('method', 'post');
    expect($creates)->toHaveCount(1);
});

it('404s the checkout click when Stripe is disabled', function () {
    StudioSettings::query()->update(['payments_stripe_enabled' => false]);
    [$project, $contact, , $installment] = m04PlanWithInstallment();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen")
        ->assertNotFound();
});

it('404s the checkout click for a project the contact is not on', function () {
    [$project, , , $installment] = m04PlanWithInstallment();
    $stranger = Contact::factory()->create();

    $this->actingAs($stranger, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen")
        ->assertNotFound();
});

it('404s the checkout click for an installment already paid', function () {
    [$project, $contact, , $installment] = m04PlanWithInstallment();
    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen")
        ->assertNotFound();
});

it('confirms a successful payment synchronously when the client is redirected back, and unlocks exactly what a transfer would', function () {
    Event::fake([InstallmentPaid::class]);
    [$project, $contact, , $installment] = m04PlanWithInstallment();
    $installment->update(['stripe_checkout_session_id' => 'cs_test_123']);

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse(paymentStatus: 'paid')]);
    ApiRequestor::setHttpClient($fake);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen?status=success&session_id=cs_test_123")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['plan']['installments'][0]['status'])->toBe('paid');
    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('never calls Stripe on an ordinary visit to the payments screen', function () {
    [$project, $contact] = m04PlanWithInstallment();

    $fake = new FakeStripeHttpClient([]);
    ApiRequestor::setHttpClient($fake);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertOk();

    expect($fake->requests)->toBeEmpty();
});

it('never puts the Stripe secret key anywhere in the checkout redirect response', function () {
    [$project, $contact, , $installment] = m04PlanWithInstallment();

    $fake = new FakeStripeHttpClient([m04CheckoutSessionResponse()]);
    ApiRequestor::setHttpClient($fake);

    $response = $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/jetzt-zahlen");

    expect($response->getContent())->not->toContain('sk_test_abc123');
});
