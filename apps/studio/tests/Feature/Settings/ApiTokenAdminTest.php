<?php

use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Integration\Models\OAuthClient;
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

it('registers, edits and deletes an OAuth connection', function () {
    $admin = test()->actingAs(staffMember(StaffRole::Admin), 'staff');

    $admin->post('/einstellungen/api-token/verbindungen', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertRedirect('/einstellungen/api-token');

    $client = OAuthClient::query()->sole();
    expect($client->client_name)->toBe('Claude')
        ->and(props('/einstellungen/api-token')['connections'][0]['id'])->toBe($client->public_id);

    $admin->patch("/einstellungen/api-token/verbindungen/{$client->public_id}", [
        'client_name' => 'Claude Web',
        'redirect_uris' => ['https://claude.ai/other'],
    ])->assertRedirect('/einstellungen/api-token');

    expect($client->fresh())->client_name->toBe('Claude Web')->redirect_uris->toBe(['https://claude.ai/other']);

    $admin->delete("/einstellungen/api-token/verbindungen/{$client->public_id}")->assertRedirect('/einstellungen/api-token');

    expect(OAuthClient::query()->count())->toBe(0);
});

it('refuses a redirect uri that is neither https nor loopback', function () {
    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post('/einstellungen/api-token/verbindungen', [
            'client_name' => 'Evil',
            'redirect_uris' => ['http://evil.example/callback'],
        ])->assertInvalid(['redirect_uris.0']);
});

it('cuts off the tokens a deleted connection issued and keeps the others', function () {
    $user = staffMember(StaffRole::Admin);
    $client = OAuthClient::create(['client_id' => 'abc', 'client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb']]);
    $user->createToken('mcp', ['manage_contacts'])->accessToken->forceFill(['oauth_client_id' => 'abc'])->save();
    $user->createToken('mcp', ['manage_contacts']);

    test()->actingAs($user, 'staff')->delete("/einstellungen/api-token/verbindungen/{$client->public_id}");

    expect($user->tokens()->count())->toBe(1);
});

it('hides OAuth connection management from every non-owner role', function (StaffRole $role) {
    $client = OAuthClient::create(['client_id' => 'abc', 'client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb']]);
    $payload = ['client_name' => 'x', 'redirect_uris' => ['https://claude.ai/cb']];

    test()->actingAs(staffMember($role), 'staff')->post('/einstellungen/api-token/verbindungen', $payload)->assertNotFound();
    test()->actingAs(staffMember($role), 'staff')->patch("/einstellungen/api-token/verbindungen/{$client->public_id}", $payload)->assertNotFound();
    test()->actingAs(staffMember($role), 'staff')->delete("/einstellungen/api-token/verbindungen/{$client->public_id}")->assertNotFound();

    expect($client->fresh())->client_name->toBe('Claude');
})->with([StaffRole::Manager, StaffRole::Contributor]);

it('edits a token\'s name and scopes without changing its secret', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $bearer = 'Bearer '.bearerToken($token);

    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/api-token/{$token->public_id}", ['name' => 'Zapier', 'scopes' => ['manage_leads']])
        ->assertRedirect('/einstellungen/api-token');

    expect($token->fresh())->name->toBe('Zapier')->scopes->toBe(['manage_leads']);

    useSurface(Surface::API);
    test()->withHeader('Authorization', $bearer)->getJson('/api/contacts')->assertForbidden();
});

it('does not edit a revoked token and hides editing from non-owner roles', function () {
    $revoked = ApiToken::factory()->create();
    $revoked->revoke();
    $active = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();
    $payload = ['name' => 'x', 'scopes' => ['manage_leads']];

    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/api-token/{$revoked->public_id}", $payload)->assertNotFound();
    test()->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->patch("/einstellungen/api-token/{$active->public_id}", $payload)->assertNotFound();

    expect($active->fresh()->scopes)->toBe(['manage_contacts']);
});
