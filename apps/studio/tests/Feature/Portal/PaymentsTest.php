<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Models\StudioSettings;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    // StudioSettings::current() caches the settings row (T-01) — several tests
    // below write straight to the row via the model, bypassing the resolver's
    // own cache-busting set(), so the cache needs clearing itself.
    Cache::store('redis')->flush();
    // T-08's "see payment status" switch defaults to off (see
    // tests/Feature/Portal/CapabilitySwitchesTest.php for that default and
    // the 404 it produces while off) — every test below exercises the
    // payment screen itself, so the switch is on for all of them.
    StudioSettings::query()->update(['portal_payment_status_enabled' => true]);
});

function t07PortalProjectWithPlan(): array
{
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create(['total' => 100000, 'reference' => 'RE-2026-42']);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 100000,
        'due_on' => now()->addWeek()->toDateString(),
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);

    return [$project, $contact, $plan];
}

it('always shows the bank transfer details, with the plan\'s own reference as Verwendungszweck, Stripe disabled or not', function () {
    StudioSettings::query()->update([
        'bank_account_holder' => 'Atelier Nordlicht Fotografie GmbH',
        'bank_iban' => 'DE89 3704 0044 0532 0130 00',
        'bank_bic' => 'COBADEFFXXX',
        'payments_stripe_enabled' => false,
    ]);

    [$project, $contact] = t07PortalProjectWithPlan();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['stripeEnabled'])->toBeFalse()
        ->and($props['bankDetails']['iban'])->toBe('DE89 3704 0044 0532 0130 00')
        ->and($props['bankDetails']['accountHolder'])->toBe('Atelier Nordlicht Fotografie GmbH')
        ->and($props['plan']['reference'])->toBe('RE-2026-42')
        ->and($props['plan']['openTotal'])->toBe(100000);
});

it('exposes stripeEnabled true only once the studio has switched it on', function () {
    StudioSettings::query()->update(['payments_stripe_enabled' => true]);

    [$project, $contact] = t07PortalProjectWithPlan();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['stripeEnabled'])->toBeTrue();
});

it('renders the missing page when the job has no payment plan yet', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $page = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page');

    expect($page['component'])->toBe('Projects/Missing')
        ->and($page['props']['kind'])->toBe('payments');
});

it('404s the payments screen for a project the contact is not on', function () {
    [$project] = t07PortalProjectWithPlan();
    $stranger = Contact::factory()->create();

    $this->actingAs($stranger, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertNotFound();
});

it('404s the payments screen for a project the studio has not made portal-visible', function () {
    $project = ClientCase::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    markBooked($project);
    PaymentPlan::factory()->for($project, 'case')->create(['total' => 0]);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertNotFound();
});

it('never puts an integer identifier into the portal payments screen', function () {
    [$project, $contact] = t07PortalProjectWithPlan();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

it('never puts the Stripe secret key anywhere in the portal payments response body', function () {
    StudioSettings::query()->update([
        'payments_stripe_enabled' => true,
        'payments_stripe_test_secret_key' => 'sk_test_portalLeakCheck',
    ]);

    [$project, $contact] = t07PortalProjectWithPlan();

    $response = $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertOk();

    expect($response->getContent())->not->toContain('sk_test_portalLeakCheck');
});

it('lists payments among the booking\'s documents once a plan exists', function () {
    [$project, $contact] = t07PortalProjectWithPlan();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['project']['documents'])->pluck('kind')->all())->toContain('payments');
});

it('counts a rate invoiced outside the plan, and calls only a past due date overdue', function () {
    StudioSettings::query()->update(['tax_number' => '22/123/45678']);
    [$project, $contact] = t07PortalProjectWithPlan();
    $project->paymentPlan->installments()->update(['due_on' => today()->toDateString()]);

    $invoice = app(IssueInvoice::class)->positions($contact, $project, [
        ['title' => 'Anzahlung', 'quantity' => 1, 'unit_price' => 50000],
    ], today()->subDay()->toDateString(), [], null);
    $invoice->installments()->update(['status' => PaymentInstallmentStatus::Paid, 'paid_amount' => $invoice->amount()]);

    $plan = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props']['plan'];

    expect($plan['total'])->toBe(100000 + $invoice->amount())
        ->and($plan['openTotal'])->toBe(100000)
        ->and(collect($plan['installments'])->pluck('isLate')->all())->toBe([false, false]);

    PaymentInstallment::query()->whereNull('invoice_id')->update(['due_on' => today()->subDay()->toDateString()]);

    expect(PaymentInstallment::query()->whereNull('invoice_id')->first()->isLate())->toBeTrue();
});
