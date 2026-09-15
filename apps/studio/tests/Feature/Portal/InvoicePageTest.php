<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Enums\CancellationKind;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    // T-08's "see payment status" switch gates this page as it gates the
    // plan screen it is reached from — see CapabilitySwitchesTest.
    StudioSettings::query()->update([
        'portal_payment_status_enabled' => true,
        'tax_number' => '22/123/45678',
    ]);
});

/** @return array{0: Project, 1: Contact, 2: PaymentInstallment} */
function portalIssuedInvoice(): array
{
    Storage::fake('local');
    Bus::fake();

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create(['street' => 'Gartenweg 4', 'postcode' => '22765', 'city' => 'Hamburg']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 100_000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 100_000,
        'due_on' => now()->addWeek()->toDateString(),
    ]);

    app(IssueInvoice::class)($installment);

    return [$project, $contact, $installment->fresh()];
}

it('shows the invoice as a page, with the recipient, the meta rows and the summary chain', function () {
    [$project, $contact, $installment] = portalIssuedInvoice();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['invoice']['number'])->toBe($installment->invoice_number)
        ->and(collect($props['invoice']['metaRows'])->pluck(0)->all())
        ->toContain('Rechnungsnummer', 'Rechnungsdatum', 'Fällig am')
        ->and(collect($props['invoice']['summaryRows'])->pluck(0)->all())
        ->toContain('Netto', 'Gesamtbetrag')
        ->and($props['project']['name'])->toBe($project->name);
});

it('drops the pay button on a cancelled invoice and keeps the page readable', function () {
    [$project, $contact, $installment] = portalIssuedInvoice();

    app(CancelInvoice::class)($installment, CancellationKind::Void, 'Termin abgesagt');

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['invoice']['statusLabel'])->toBe('Storniert')
        ->and($props['canPay'])->toBeFalse();
});

it('404s the page for a contact who cannot see the project', function () {
    [$project, , $installment] = portalIssuedInvoice();

    $this->actingAs(Contact::factory()->create(), 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}")
        ->assertNotFound();
});

it('404s the page for an installment that was never issued', function () {
    [$project, $contact] = portalIssuedInvoice();

    $sibling = PaymentInstallment::factory()
        ->for($project->job->paymentPlan, 'plan')
        ->create(['amount' => 0, 'due_on' => now()->addMonth()->toDateString()]);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$sibling->public_id}")
        ->assertNotFound();
});

it('404s the page while the payment-status switch is off', function () {
    [$project, $contact, $installment] = portalIssuedInvoice();

    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_payment_status_enabled' => false]);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$installment->public_id}")
        ->assertNotFound();
});
