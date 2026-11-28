<?php

use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\ContractMode;
use App\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// renderedContract() lives in tests/Pest.php — see the note there.

// T-08's "sign contracts" switch defaults to off (see
// tests/Feature/Portal/CapabilitySwitchesTest.php for that default and the
// 404 it produces while off) — every test below exercises the sign action
// itself, so the switch is on for all of them. Cache::flush() first: this
// writes straight to the row via the model, bypassing the resolver's own
// cache-busting set() (T-01), so a row already cached by an earlier test in
// the same suite run would otherwise still read as off.
beforeEach(function () {
    useSurface(Surface::PORTAL);
    Cache::store('redis')->flush();
    StudioSettings::query()->update(['portal_sign_contracts_enabled' => true]);
});

it('signs the rendered contract when the consent box is explicitly checked', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project, '%PDF-stored-bytes');
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertRedirect();

    $signature = ContractSignature::sole();
    expect($signature->contract_id)->toBe($contract->id)
        ->and($signature->contact_id)->toBe($contact->id)
        ->and($signature->typed_name)->toBe('Lena Meier')
        ->and($signature->document_sha256)->toBe(hash('sha256', '%PDF-stored-bytes'));
});

it('rejects the submission server-side when consent is not checked, even though the button is disabled client-side', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => false])
        ->assertSessionHasErrors('consent');

    expect(ContractSignature::count())->toBe(0);
});

it('rejects the submission when consent is entirely absent from the request', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier'])
        ->assertSessionHasErrors('consent');

    expect(ContractSignature::count())->toBe(0);
});

it('rejects the submission when the typed name is blank', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => '', 'consent' => true])
        ->assertSessionHasErrors('typedName');

    expect(ContractSignature::count())->toBe(0);
});

it('shows the signature once the contract is signed and never a second submit', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true]);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['contract']['signature']['typedName'])->toBe('Lena Meier')
        ->and($props['contract']['signature']['consentedAt'])->not->toBeNull();

    // Signing again is a no-op, not a second row.
    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Someone Else', 'consent' => true]);

    expect(ContractSignature::count())->toBe(1)
        ->and(ContractSignature::sole()->typed_name)->toBe('Lena Meier');
});

it('404s a sign attempt for a project the contact is not on', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertNotFound();

    expect(ContractSignature::count())->toBe(0);
});

it('404s a sign attempt when the project has no contract yet', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertNotFound();
});

it('refuses to sign and hides the form while contracts are off', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    StudioSettings::query()->update(['contract_mode' => ContractMode::Off->value]);
    Cache::store('redis')->flush();

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertInertia(fn ($page) => $page->where('canSign', false));

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertForbidden();

    expect(ContractSignature::count())->toBe(0);
});
