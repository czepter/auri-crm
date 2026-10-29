<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
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

    expect(ClientCase::count())->toBe(1)
        ->and(Lead::count())->toBe(1)
        ->and(Lead::first()->captured_by_id)->toBeNull()
        ->and($response->json('lead.stage'))->toBe('new');

    expect(integerIdPaths($response->json()))->toBe([]);
});

// A wedding inquiry is often written by the partner, and the answer goes to
// both: the second person must arrive as a contact of their own.
it('adds everyone on the inquiry as a contact of the project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lead-people')
        ->postJson('/api/leads', [
            'contact_name' => 'Viktoria Böser',
            'contact_email' => 'viktoria@example.test',
            'people' => [['name' => 'Manuel', 'email' => 'manuel@example.test']],
            'project_name' => 'Hochzeit Viktoria Böser & Manuel',
            'type' => 'wedding',
            'source' => 'website',
            'captured_via' => 'email',
        ])
        ->assertCreated();

    expect(ClientCase::first()->contacts->pluck('email', 'name')->all())->toBe([
        'Viktoria Böser' => 'viktoria@example.test',
        'Manuel' => 'manuel@example.test',
    ]);
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
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => LeadStage::Contacted->value]);

    $response->assertOk()->assertJson(['id' => $lead->case->public_id, 'stage' => 'contacted']);
    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});

it('rejects an illegal stage transition the same way the board does', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->atStage(LeadStage::Booked)->create();

    // Backwards is legal now — the no-op is the transition left to reject.
    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-illegal')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => LeadStage::Booked->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Booked->value);
});

it('refuses to advance a stage without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-scope')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => LeadStage::Contacted->value])
        ->assertForbidden();

    expect($lead->fresh()->stage->key)->toBe(LeadStage::New->value);
});

it('refuses to move a lead onto or off the won stage without book_project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $open = Lead::factory()->atStage(LeadStage::QuoteAccepted)->create();
    $won = Lead::factory()->atStage(LeadStage::Booked)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'won-1')
        ->postJson("/api/cases/{$open->case->public_id}/lead/stage", ['stage' => LeadStage::Booked->value])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'won-2')
        ->postJson("/api/cases/{$won->case->public_id}/lead/stage", ['stage' => LeadStage::QuoteAccepted->value])
        ->assertForbidden();

    expect($open->case->fresh()->isBooked())->toBeFalse()
        ->and($won->fresh()->stage->key)->toBe(LeadStage::Booked->value);
});

it('books through the won stage with book_project', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads, Capability::BookProject)->create();
    $lead = Lead::factory()->atStage(LeadStage::QuoteAccepted)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'won-3')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => LeadStage::Booked->value])
        ->assertOk();

    expect($lead->case->fresh()->isBooked())->toBeTrue();
});

it('404s advancing a stage for a lead public_id from another tenant or that does not exist', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-missing')
        ->postJson('/api/cases/does-not-exist-1234567890/lead/stage', ['stage' => LeadStage::Contacted->value])
        ->assertNotFound();
});

it('closes a lead and takes that back', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lost-1')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertOk();

    expect($lead->fresh()->isLost())->toBeTrue();

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-1')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/reopen");

    $response->assertOk();

    expect($lead->fresh()->isLost())->toBeFalse()
        ->and($response->json('closedAt'))->toBeNull()
        ->and(integerIdPaths($response->json()))->toBe([]);
});

it('refuses to close a lead without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'lost-scope')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertForbidden();

    expect($lead->fresh()->isLost())->toBeFalse();
});

it('rewrites a lead\'s inquiry text after capture', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create(['inquiry_text' => 'Tippfehlr']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'inquiry-1')
        ->patchJson("/api/cases/{$lead->case->public_id}/lead", ['inquiry_text' => 'Tippfehler'])
        ->assertOk();

    expect($lead->fresh()->inquiry_text)->toBe('Tippfehler')
        ->and($response->json('inquiryText'))->toBe('Tippfehler')
        ->and(integerIdPaths($response->json()))->toBe([]);
});

// #363: captured_via had no way back after capture. captured_via_other rides
// along, but only survives when the channel is Other — Lead::booted() clears
// it otherwise, so the request itself has no need to null it explicitly.
it('corrects a lead\'s capture channel after the fact', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create(['inquiry_text' => 'x', 'captured_via' => 'phone']);

    $response = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'captured-via-1')
        ->patchJson("/api/cases/{$lead->case->public_id}/lead", [
            'inquiry_text' => 'x',
            'captured_via' => 'other',
            'captured_via_other' => 'Hochzeitsmesse-Stand',
        ])
        ->assertOk();

    expect($lead->fresh()->captured_via->value)->toBe('other')
        ->and($lead->fresh()->captured_via_other)->toBe('Hochzeitsmesse-Stand')
        ->and($response->json('capturedVia'))->toBe('other')
        ->and(integerIdPaths($response->json()))->toBe([]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'captured-via-2')
        ->patchJson("/api/cases/{$lead->case->public_id}/lead", ['inquiry_text' => 'x', 'captured_via' => 'email'])
        ->assertOk();

    expect($lead->fresh()->captured_via_other)->toBeNull();
});
