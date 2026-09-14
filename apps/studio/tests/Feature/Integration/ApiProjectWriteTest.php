<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

// #400: LeadCaseController::update() nulls type_other whenever `type` arrives
// without it. The api surface must not repeat that — only a key the request
// actually sends is written.
it('updates a project, merging rather than replacing untouched fields', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create(['name' => 'Voss — Portrait', 'type' => 'other', 'type_other' => 'Bewerbungsfotos']);
    Lead::factory()->for($project)->create(['source' => 'website']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'project-update-1')
        ->patchJson("/api/projects/{$project->public_id}", ['name' => 'Voss — Bewerbung']);

    $response->assertOk()->assertJson(['name' => 'Voss — Bewerbung', 'type' => 'other', 'typeOther' => 'Bewerbungsfotos']);

    expect($project->fresh()->type_other)->toBe('Bewerbungsfotos')
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('updates the lead source alongside the project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create();
    $lead = Lead::factory()->for($project)->create(['source' => 'website']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'project-update-source')
        ->patchJson("/api/projects/{$project->public_id}", ['source' => 'other', 'source_other' => 'Hochzeitsportal']);

    $response->assertOk()->assertJson(['source' => 'other', 'sourceOther' => 'Hochzeitsportal']);
    expect($lead->fresh()->source_other)->toBe('Hochzeitsportal');
});

it('refuses a project update without the manage_shoots scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ViewAllProjects)->create();
    $project = Project::factory()->create(['name' => 'Voss — Portrait']);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'project-update-scope')
        ->patchJson("/api/projects/{$project->public_id}", ['name' => 'Someone Else'])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Voss — Portrait');
});

// #400: LeadContactController::destroy()'s primary refusal, mirrored here.
it('removes a secondary project contact but refuses to remove the primary one', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageShoots)->create();
    $project = Project::factory()->create();
    $primary = Contact::factory()->create();
    $partner = Contact::factory()->create();
    $project->attachContact($primary, ProjectContactRole::Primary);
    $project->attachContact($partner, ProjectContactRole::Partner);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'remove-primary')
        ->deleteJson("/api/projects/{$project->public_id}/contacts/{$primary->public_id}")
        ->assertUnprocessable();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'remove-partner')
        ->deleteJson("/api/projects/{$project->public_id}/contacts/{$partner->public_id}")
        ->assertNoContent();

    expect($project->contacts()->pluck('contacts.id')->all())->toBe([$primary->id]);
});
