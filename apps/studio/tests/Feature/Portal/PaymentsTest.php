<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    // StudioSettingsResolver caches the settings row (T-01) — several tests
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
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 100000, 'reference' => 'RE-2026-42']);
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

it('shows an empty plan when the job has no payment plan yet', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['plan'])->toBeNull();
});

it('404s the payments screen for a project the contact is not on', function () {
    [$project] = t07PortalProjectWithPlan();
    $stranger = Contact::factory()->create();

    $this->actingAs($stranger, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertNotFound();
});

it('404s the payments screen for a project the studio has not made portal-visible', function () {
    $project = Project::factory()->create(['portal_visible' => false]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 0]);

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

it('serves a Gutschrift for a paid installment that was never invoiced', function () {
    Storage::fake('local');
    Storage::disk('local')->put('credit-notes/gs-1.pdf', '%PDF-fake-bytes');

    [$project, $contact, $plan] = t07PortalProjectWithPlan();
    $installment = $plan->installments()->first();
    $installment->update(['status' => PaymentInstallmentStatus::Paid, 'paid_on' => '2026-09-02']);
    $note = $installment->creditNotes()->create([
        'number' => 'GS-2026-1',
        'issued_at' => now(),
        'amount' => 20000,
        'reason' => 'Zweite Kamera nicht erbracht',
        'pdf_path' => 'credit-notes/gs-1.pdf',
    ]);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['plan']['installments'][0]['invoiceNumber'])->toBeNull()
        ->and($props['plan']['installments'][0]['creditNotes'][0]['hasDocument'])->toBeTrue();

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}/gutschrift/{$note->public_id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
