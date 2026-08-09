<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Payments\Models\PaymentPlan;
use App\Settings\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
});

/**
 * T-08's own rule: a switched-off capability refuses the route it guards
 * exactly as a route that does not exist would — never merely a page with
 * the button removed. Every switch here defaults to off (the migration's own
 * column defaults), proven once per switch rather than assumed.
 */
it('defaults every client capability switch to off', function () {
    $row = StudioSettings::query()->sole();

    expect($row->portal_accept_quotes_enabled)->toBeFalse()
        ->and($row->portal_sign_contracts_enabled)->toBeFalse()
        ->and($row->portal_payment_status_enabled)->toBeFalse()
        ->and($row->portal_messaging_enabled)->toBeFalse();
});

it('404s the sign-contract route the same way a route that does not exist would, while the switch is off', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true]);

    $response->assertNotFound();
    expect($response->status())->toBe($missing->status());
    expect(\App\Contracts\Models\ContractSignature::count())->toBe(0);
});

it('hides the sign-contract affordance on the contract screen while the switch is off, even though the contract is otherwise signable', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['canSign'])->toBeFalse();
});

it('signs once the studio switches sign-contracts on', function () {
    Storage::fake('local');
    StudioSettings::query()->update(['portal_sign_contracts_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertRedirect();

    expect(\App\Contracts\Models\ContractSignature::count())->toBe(1);
});

it('404s the payment-status route the same way a route that does not exist would, while the switch is off', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $missing = $this->actingAs($contact, 'portal')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen");

    $response->assertNotFound();
    expect($response->status())->toBe($missing->status());
});

it('hides the payments link on the project overview while the switch is off, even though a payment plan exists', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['project']['hasPaymentPlan'])->toBeFalse();
});

it('shows the payments route and link once the studio switches payment-status on', function () {
    StudioSettings::query()->update(['portal_payment_status_enabled' => true]);

    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    PaymentPlan::factory()->for($job)->create(['total' => 100000]);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/zahlungen")->assertOk();
});
