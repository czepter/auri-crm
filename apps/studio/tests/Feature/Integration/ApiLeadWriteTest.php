<?php

use App\Crm\Enums\LeadStage;
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

it('captures a lead, reusing CaptureInquiry exactly like the admin capture form', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lead-1')
        ->postJson('/api/leads', [
            'contact_name' => 'Nina Voss',
            'contact_email' => 'nina@example.test',
            'project_name' => 'Voss — Portrait',
            'type' => 'portrait',
            'source' => 'website',
            'captured_via' => 'other',
            'date' => null,
            'inquiry_text' => 'Interested in a portrait session.',
        ]);

    $response->assertCreated();

    expect(Project::count())->toBe(1)
        ->and(Lead::count())->toBe(1)
        ->and(Lead::first()->captured_by_id)->toBeNull()
        ->and($response->json('lead.stage'))->toBe('new');

    expect(integerIdPaths($response->json()))->toBe([]);
});

// #351: a platform lead names its platform — LeadSource stays countable and
// the name lands in source_other.
it('carries source_other onto the lead when the source is other', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lead-source-other')
        ->postJson('/api/leads', [
            'contact_name' => 'Nina Voss',
            'project_name' => 'Voss — Firmenfeier',
            'type' => 'portrait',
            'source' => 'other',
            'source_other' => 'Hochzeitsportal',
            'captured_via' => 'phone',
        ])
        ->assertCreated();

    expect(Lead::first()->source_other)->toBe('Hochzeitsportal');
});

it('refuses to capture a lead without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lead-scope')
        ->postJson('/api/leads', ['project_name' => 'x', 'type' => 'portrait', 'source' => 'website', 'captured_via' => 'other', 'contact_name' => 'x'])
        ->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('advances a lead stage, reusing AdvanceLeadStage exactly like the board', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-1')
        ->postJson("/api/leads/{$lead->public_id}/stage", ['stage' => LeadStage::Contacted->value]);

    $response->assertOk()->assertJson(['id' => $lead->public_id, 'stage' => 'contacted']);
    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});

it('rejects an illegal stage transition the same way the board does', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->atStage(LeadStage::Booked)->create();

    // Backwards is legal now — the no-op is the transition left to reject.
    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-illegal')
        ->postJson("/api/leads/{$lead->public_id}/stage", ['stage' => LeadStage::Booked->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Booked->value);
});

it('refuses to advance a stage without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-scope')
        ->postJson("/api/leads/{$lead->public_id}/stage", ['stage' => LeadStage::Contacted->value])
        ->assertForbidden();

    expect($lead->fresh()->stage->key)->toBe(LeadStage::New->value);
});

it('404s advancing a stage for a lead public_id from another tenant or that does not exist', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-missing')
        ->postJson('/api/leads/does-not-exist-1234567890/stage', ['stage' => LeadStage::Contacted->value])
        ->assertNotFound();
});

it('closes a lead and takes that back', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lost-1')
        ->postJson("/api/leads/{$lead->public_id}/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertOk();

    expect($lead->fresh()->isLost())->toBeTrue();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-1')
        ->postJson("/api/leads/{$lead->public_id}/reopen");

    $response->assertOk();

    expect($lead->fresh()->isLost())->toBeFalse()
        ->and($response->json('lostAt'))->toBeNull()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses to close a lead without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lost-scope')
        ->postJson("/api/leads/{$lead->public_id}/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertForbidden();

    expect($lead->fresh()->isLost())->toBeFalse();
});
