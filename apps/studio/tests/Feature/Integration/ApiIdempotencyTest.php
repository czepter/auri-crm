<?php

use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Integration\Models\IdempotencyKey;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
});

/**
 * I-03's acceptance criterion, proven directly against a real write route
 * (POST /api/contacts) rather than the middleware in isolation — the same
 * "replaying with the same key returns the original result and changes
 * nothing" wording the issue itself uses.
 */
it('replays the exact same response and writes exactly once for a repeated key and payload', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $payload = ['name' => 'Lena Meier', 'email' => 'lena@example.test'];

    $first = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'replay-1')
        ->postJson('/api/contacts', $payload);

    $second = $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'replay-1')
        ->postJson('/api/contacts', $payload);

    $first->assertCreated();
    $second->assertCreated();
    expect($second->json())->toBe($first->json());
    expect(Contact::count())->toBe(1);
});

it('executes again for a different idempotency key with the same payload', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $payload = ['name' => 'Lena Meier'];

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'key-a')
        ->postJson('/api/contacts', $payload)
        ->assertCreated();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'key-b')
        ->postJson('/api/contacts', $payload)
        ->assertCreated();

    expect(Contact::count())->toBe(2);
});

it('rejects a write with no Idempotency-Key header at all', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->postJson('/api/contacts', ['name' => 'Lena Meier'])
        ->assertStatus(400);

    expect(Contact::count())->toBe(0);
});

it('scopes idempotency keys per token, so two tokens reusing the same key both execute', function () {
    $tokenA = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $tokenB = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($tokenA))
        ->withHeader('Idempotency-Key', 'shared-key')
        ->postJson('/api/contacts', ['name' => 'Token A Contact'])
        ->assertCreated();

    Auth::forgetGuards(); // see ApiTokenAuthenticationTest's own note on why

    $this->withHeader('Authorization', 'Bearer '.bearerToken($tokenB))
        ->withHeader('Idempotency-Key', 'shared-key')
        ->postJson('/api/contacts', ['name' => 'Token B Contact'])
        ->assertCreated();

    expect(Contact::count())->toBe(2);
});

it('rejects reusing a key against a materially different payload', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'mismatched')
        ->postJson('/api/contacts', ['name' => 'Lena Meier'])
        ->assertCreated();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'mismatched')
        ->postJson('/api/contacts', ['name' => 'A Totally Different Person'])
        ->assertStatus(422);

    expect(Contact::count())->toBe(1);
});

it('never leaves a stuck claim behind a validation failure, so a corrected retry under the same key succeeds', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'fix-and-retry')
        ->postJson('/api/contacts', ['email' => 'missing-name@example.test'])
        ->assertUnprocessable();

    expect(IdempotencyKey::count())->toBe(0)
        ->and(Contact::count())->toBe(0);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'fix-and-retry')
        ->postJson('/api/contacts', ['name' => 'Lena Meier'])
        ->assertCreated();

    expect(Contact::count())->toBe(1);
});

it('409s a second caller racing a still in-flight claim for the same key', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    // Simulates the claim another request already inserted but has not yet
    // finished — response_status stays null until EnsureIdempotentWrite's
    // own $next() call returns.
    IdempotencyKey::create([
        'principal_type' => ApiToken::class,
        'principal_id' => $token->id,
        'idempotency_key' => 'in-flight',
        'request_fingerprint' => hash('sha256', 'POST|api/contacts|'.json_encode(['name' => 'Lena Meier'])),
    ]);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'in-flight')
        ->postJson('/api/contacts', ['name' => 'Lena Meier'])
        ->assertStatus(409);

    expect(Contact::count())->toBe(0);
});

/**
 * #395: a claim used to replay forever, so closing a lead, reopening it and
 * closing it again with the same reason left it open — the third call just
 * replayed the first response. Once the claim's window has passed, the
 * same write executes again.
 */
it('closes a lead again after the same-key claim from an earlier close has expired', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'close-1')
        ->postJson("/api/leads/{$lead->public_id}/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'reopen-1')
        ->postJson("/api/leads/{$lead->public_id}/reopen")
        ->assertOk();

    expect($lead->fresh()->isLost())->toBeFalse();

    $this->travel(IdempotencyKey::WINDOW_HOURS + 1)->hours();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'close-1')
        ->postJson("/api/leads/{$lead->public_id}/lost", ['outcome' => 'lost', 'reason' => 'Budget'])
        ->assertOk();

    expect($lead->fresh()->isLost())->toBeTrue();
});
