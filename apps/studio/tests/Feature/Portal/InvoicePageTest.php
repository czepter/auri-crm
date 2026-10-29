<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Enums\CancellationKind;
use App\Payments\Models\Invoice;
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

/** @return array{0: ClientCase, 1: Contact, 2: Invoice} */
function portalIssuedInvoice(): array
{
    Storage::fake('local');
    Bus::fake();

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create(['street' => 'Gartenweg 4', 'postcode' => '22765', 'city' => 'Hamburg']);
    $project->attachContact($contact, CaseContactRole::Primary);

    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create(['total' => 100_000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 100_000,
        'due_on' => now()->addWeek()->toDateString(),
    ]);

    return [$project, $contact, app(IssueInvoice::class)($installment)->fresh()];
}

it('shows the invoice as a page, with the recipient, the meta rows and the summary chain', function () {
    [$project, $contact, $invoice] = portalIssuedInvoice();

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$invoice->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['invoice']['number'])->toBe($invoice->number)
        ->and(collect($props['invoice']['metaRows'])->pluck(0)->all())
        ->toContain('Rechnungsnummer', 'Rechnungsdatum', 'Fällig am')
        ->and(collect($props['invoice']['summaryRows'])->pluck(0)->all())
        ->toContain('Netto', 'Gesamtbetrag')
        ->and($props['project']['name'])->toBe($project->name);
});

it('drops the pay button on a cancelled invoice and keeps the page readable', function () {
    [$project, $contact, $invoice] = portalIssuedInvoice();

    app(CancelInvoice::class)($invoice, CancellationKind::Void, 'Termin abgesagt');

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$invoice->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['invoice']['statusLabel'])->toBe('Storniert')
        ->and($props['payInstallmentId'])->toBeNull();
});

it('404s the page for a contact who cannot see the project', function () {
    [$project, , $invoice] = portalIssuedInvoice();

    $this->actingAs(Contact::factory()->create(), 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$invoice->public_id}")
        ->assertNotFound();
});

it('404s the page for a rate that was never issued', function () {
    [$project, $contact] = portalIssuedInvoice();

    $sibling = PaymentInstallment::factory()
        ->for($project->paymentPlan, 'plan')
        ->create(['amount' => 0, 'due_on' => now()->addMonth()->toDateString()]);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$sibling->public_id}")
        ->assertNotFound();
});

it('404s the page while the payment-status switch is off', function () {
    [$project, $contact, $invoice] = portalIssuedInvoice();

    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_payment_status_enabled' => false]);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/zahlungen/{$invoice->public_id}")
        ->assertNotFound();
});

// --- #415: die Rechnungsliste am Projekt und der erste Blick ------------

it('lists only invoices on the project page', function () {
    [$project, $contact, $invoice] = portalIssuedInvoice();

    PaymentInstallment::factory()->for($invoice->plan(), 'plan')->create([
        'amount' => 50_000,
        'due_on' => now()->addMonth()->toDateString(),
        'sort_order' => 1,
    ]);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['project']['invoices'])->toHaveCount(1)
        ->and($props['project']['invoices'][0]['number'])->toBe($invoice->number)
        ->and($props['project']['invoices'][0]['amount'])->toBe(100_000);
});

it('records every look at the invoice', function () {
    [$project, $contact, $invoice] = portalIssuedInvoice();

    expect($invoice->views()->count())->toBe(0);

    foreach ([1, 2] as $_) {
        $this->actingAs($contact, 'portal')
            ->get("/projekte/{$project->public_id}/zahlungen/{$invoice->public_id}")
            ->assertOk();
    }

    expect($invoice->views()->where('via', 'portal')->count())->toBe(2);
});
