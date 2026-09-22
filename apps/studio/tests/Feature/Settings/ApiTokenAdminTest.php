<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the plaintext token once, right after creating it', function () {
    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post('/einstellungen/api-token', ['name' => 'n8n', 'scopes' => ['manage_contacts']])
        ->assertRedirect('/einstellungen/api-token');

    $secret = props('/einstellungen/api-token')['flash']['secret'];
    $token = ApiToken::query()->sole();

    expect($secret)->toBeString()->toContain('|')
        ->and($token->scopes)->toBe(['manage_contacts'])
        ->and($token->tokens()->sole()->token)->toBe(hash('sha256', explode('|', $secret, 2)[1]))
        ->and(props('/einstellungen/api-token')['flash']['secret'])->toBeNull();
});

it('rejects an unknown scope', function () {
    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post('/einstellungen/api-token', ['name' => 'n8n', 'scopes' => ['not_a_capability']])
        ->assertInvalid(['scopes.0']);
});

it('revokes a token so the API refuses it', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $bearer = 'Bearer '.bearerToken($token);

    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->delete("/einstellungen/api-token/{$token->public_id}")
        ->assertRedirect('/einstellungen/api-token');

    expect(props('/einstellungen/api-token')['tokens'])->toBeEmpty();

    useSurface(Surface::API);
    test()->withHeader('Authorization', $bearer)->getJson('/api/contacts')->assertUnauthorized();
});

it('hides the api token screen from every non-owner role', function (StaffRole $role) {
    $token = ApiToken::factory()->create();

    test()->actingAs(staffMember($role), 'staff')->get('/einstellungen/api-token')->assertNotFound();
    test()->actingAs(staffMember($role), 'staff')
        ->post('/einstellungen/api-token', ['name' => 'x', 'scopes' => ['manage_contacts']])
        ->assertNotFound();
    test()->actingAs(staffMember($role), 'staff')
        ->delete("/einstellungen/api-token/{$token->public_id}")
        ->assertNotFound();

    expect(ApiToken::query()->count())->toBe(1)
        ->and($token->fresh()->isRevoked())->toBeFalse();
})->with([StaffRole::Manager, StaffRole::Contributor, StaffRole::Contributor, StaffRole::Contributor]);
