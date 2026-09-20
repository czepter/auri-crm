<?php

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// renderedContract() lives in tests/Pest.php — see the note there.

it('never dispatches a render or calls Gotenberg when the admin views an already-rendered contract', function () {
    Storage::fake('local');
    Queue::fake();
    Http::fake();

    $contract = renderedContract();
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')->get("/auftraege/{$contract->project->public_id}/vertrag")->assertOk();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('never dispatches a render or calls Gotenberg when downloading an admin contract', function () {
    Storage::fake('local');
    Queue::fake();
    Http::fake();

    $contract = renderedContract();
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')->get("/vertraege/{$contract->public_id}/pdf")->assertOk();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('never dispatches a render or calls Gotenberg when the portal views an already-rendered contract', function () {
    Storage::fake('local');
    Queue::fake();
    Http::fake();

    useSurface(Surface::PORTAL);

    $project = Project::factory()->create(['portal_visible' => true]);
    $contract = renderedContract($project);
    $contact = Contact::factory()->create();
    $contract->project->attachContact($contact, ProjectContactRole::Primary);

    $this->actingAs($contact, 'portal')->get("/projekte/{$contract->project->public_id}/vertrag")->assertOk();

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('serves the stored artifact byte-for-byte rather than anything freshly rendered', function () {
    Storage::fake('local');

    $contract = renderedContract(bytes: '%PDF-stored-bytes');
    $staff = staffMember(StaffRole::Owner);

    $response = $this->actingAs($staff, 'staff')->get("/vertraege/{$contract->public_id}/pdf");

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-stored-bytes');
});

it('404s a download for a contract that has not rendered yet', function () {
    Storage::fake('local');

    $project = Project::factory()->create();
    $contract = Contract::factory()->for($project)->create(['status' => ContractStatus::Pending]);
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')->get("/vertraege/{$contract->public_id}/pdf")->assertNotFound();
});
