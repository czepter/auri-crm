<?php

use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// renderedContract() lives in tests/Pest.php — see the note there.

beforeEach(fn () => useSurface(Surface::PORTAL));

it('signs the rendered contract when the consent box is explicitly checked', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project, '%PDF-stored-bytes');
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

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

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => false])
        ->assertSessionHasErrors('consent');

    expect(ContractSignature::count())->toBe(0);
});

it('rejects the submission when consent is entirely absent from the request', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier'])
        ->assertSessionHasErrors('consent');

    expect(ContractSignature::count())->toBe(0);
});

it('rejects the submission when the typed name is blank', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => '', 'consent' => true])
        ->assertSessionHasErrors('typedName');

    expect(ContractSignature::count())->toBe(0);
});

it('shows the signature once the contract is signed and never a second submit', function () {
    Storage::fake('local');

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

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

    $project = Project::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertNotFound();

    expect(ContractSignature::count())->toBe(0);
});

it('404s a sign attempt when the project has no contract yet', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')
        ->post("/projekte/{$project->public_id}/vertrag/unterschreiben", ['typedName' => 'Lena Meier', 'consent' => true])
        ->assertNotFound();
});
