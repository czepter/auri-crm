<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Models\DocumentTemplate;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// bookedContactProject() and renderedContract() live in tests/Pest.php.

/**
 * A contract row can say "rendered" with nothing behind it — a render lost
 * between the queue and storage, a file gone from the disk, a database seeded
 * without one. Before this the client sat at "Der Vertrag wird erstellt."
 * with nothing on its way; now the screen builds it.
 */
it('renders the missing artifact from the database when the client opens the contract', function () {
    useSurface(Surface::PORTAL);
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-rendered-on-view', 200)]);

    $template = DocumentTemplate::factory()->create(['body' => '<p>{{ contact.name }} — {{ project.name }}</p>']);
    $project = bookedContactProject();
    $project->update(['portal_visible' => true]);
    $contract = Contract::factory()->for($project, 'case')->for($template, 'template')->create([
        // The shape the symptom arrived in: rendered, pointing at nothing.
        'status' => ContractStatus::Rendered,
        'artifact_path' => 'contracts/gone/missing.pdf',
        'rendered_at' => now(),
    ]);

    $this->actingAs($project->contacts()->sole(), 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk();

    // The content is the point: the bytes come from what the database holds
    // for this project at the moment of the render, not from a stub.
    Http::assertSent(fn (HttpRequest $request) => str_contains(
        (string) $request->body(),
        'Lena Meier — Hochzeit Meier & Kowalski',
    ));

    $contract->refresh();
    expect($contract->status)->toBe(ContractStatus::Rendered)
        ->and($contract->artifact_path)->toBe("contracts/{$contract->tenant_id}/{$contract->public_id}.pdf")
        ->and($contract->hasArtifact())->toBeTrue();

    Storage::disk('local')->assertExists($contract->artifact_path);
});

it('leaves a signed contract alone even when its bytes are gone', function () {
    useSurface(Surface::PORTAL);
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('local');
    Http::fake();

    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);
    ContractSignature::factory()->for($contract)->create(['contact_id' => $contact->id]);

    // The signature holds a sha256 of exactly these bytes. Losing them must
    // never mint replacements a signature would then appear to cover.
    Storage::disk('local')->delete($contract->artifact_path);

    $this->actingAs($contact, 'portal')
        ->get("/projekte/{$project->public_id}/vertrag")
        ->assertOk();

    Http::assertNothingSent();
    expect($contract->fresh()->artifact_path)->toBe($contract->artifact_path);
});
