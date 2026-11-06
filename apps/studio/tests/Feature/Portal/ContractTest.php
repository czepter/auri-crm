<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// renderedContract() lives in tests/Pest.php — see the note there.

beforeEach(fn () => useSurface(Surface::PORTAL));

it('shows the contact their own project\'s rendered contract', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['contract']['status'])->toBe('rendered')
        ->and($props['contract']['downloadUrl'])->toBe("/projekte/{$project->public_id}/vertrag/pdf")
        ->and($props['contract']['html'])->toBeString()->not->toBeEmpty();
});

it('shows an empty state when the project has no contract yet', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['contract'])->toBeNull();
});

it('404s a contract page for a project the contact is not on', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/vertrag")->assertNotFound();
});

it('404s a contract page for a project the studio has not made portal-visible', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => false]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/vertrag")->assertNotFound();
});

it('downloads the stored artifact', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project, '%PDF-portal-bytes');
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $response = $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/vertrag/pdf");

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-portal-bytes');
});

it('never puts an integer identifier into the portal contract screen', function () {
    Storage::fake('local');

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect(integerIdPaths($props))->toBe([]);
});

// A missing artifact is rebuilt on sight now — see
// tests/Feature/Contracts/EnsureContractArtifactTest.php. What survives from
// the rule this test was written for is the case where rebuilding cannot
// happen: the screen still opens, and it still never links to bytes that are
// not there.
it('404s the download and hides the link when the artifact is gone and Gotenberg is down', function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('boom', 500)]);

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    Storage::disk('local')->delete($contract->artifact_path);

    $props = $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['contract']['downloadUrl'])->toBeNull();

    $this->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}/vertrag/pdf")->assertNotFound();
});
