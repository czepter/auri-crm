<?php

use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A PKCE pair the way an MCP client would generate one — S256 only, this
 * flow accepts nothing else. Lives here rather than in tests/Pest.php:
 * this is the only suite that needs one.
 */
function pkcePair(): array
{
    $verifier = bin2hex(random_bytes(32));
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

const REDIRECT_URI = 'http://127.0.0.1:33418/callback';
const CLAUDE_REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

it('shows the consent screen scoped to the signed-in staff member\'s own role', function () {
    $user = staffMember(StaffRole::SecondShooter);
    [, $challenge] = pkcePair();

    $props = $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => 'xyz',
    ]))->assertOk()->viewData('page')['props'];

    $granted = collect($props['scopes'])->pluck('value')->all();

    expect($granted)->toEqualCanonicalizing(
        array_map(fn (Capability $c) => $c->value, StaffRole::SecondShooter->capabilities())
    )->and($granted)->not->toContain(Capability::ManageStaff->value);

    expect(integerIdPaths($props))->toBe([]);
});

it('redirects a guest to login and back to the authorize screen after signing in', function () {
    [, $challenge] = pkcePair();

    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->get("/oauth/authorize?{$query}")->assertRedirect('/login');
});

it('rejects an unknown client_id or a redirect_uri outside the allow list', function () {
    $user = staffMember();
    [, $challenge] = pkcePair();

    $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'someone-elses-client',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertStatus(400);

    $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => 'https://evil.example/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertStatus(400);
});

it('accepts a loopback redirect_uri on any port for the same path', function () {
    $user = staffMember();
    [, $challenge] = pkcePair();

    $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => 'http://127.0.0.1:54219/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk();
});

it('completes the authorization-code-with-PKCE flow end to end and mints a working token', function () {
    $user = staffMember(StaffRole::Photographer);
    [$verifier, $challenge] = pkcePair();

    $authorize = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'state' => 'abc123',
        'decision' => 'approve',
    ]);

    $authorize->assertRedirect();
    $location = $authorize->headers->get('Location');
    expect($location)->toStartWith(REDIRECT_URI);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
    expect($params['state'])->toBe('abc123')->and($params['code'])->not->toBeEmpty();

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
    ])->assertOk()->json();

    expect($token['token_type'])->toBe('Bearer')
        ->and($token['scope'])->toBe(Capability::ManageContacts->value)
        ->and($token['access_token'])->not->toBeEmpty();

    expect(integerIdPaths($token))->toBe([]);

    useSurface(Surface::API);

    $this->withHeader('Authorization', 'Bearer '.$token['access_token'])
        ->getJson('/api/oauth/token-info')
        ->assertOk()
        ->assertJson(['clientId' => $user->public_id, 'scopes' => [Capability::ManageContacts->value]]);

    // Scoped to manage_contacts only — a leads route the token was never
    // granted must still refuse it, even though Photographer's own role
    // allows manage_leads.
    $this->withHeader('Authorization', 'Bearer '.$token['access_token'])
        ->getJson('/api/leads')
        ->assertForbidden();
});

it('grants the caller\'s full role when no scope is requested', function () {
    $user = staffMember(StaffRole::Editor);
    [$verifier, $challenge] = pkcePair();

    $authorize = $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    $scope = $authorize->viewData('page')['props']['scope'];

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => $scope,
        'decision' => 'approve',
    ]);

    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
    ])->assertOk()->json();

    expect(explode(' ', $token['scope']))->toEqualCanonicalizing(
        array_map(fn (Capability $c) => $c->value, StaffRole::Editor->capabilities())
    );
});

it('redirects with access_denied when the staff member declines', function () {
    $user = staffMember();
    [, $challenge] = pkcePair();

    $response = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'state' => 'xyz',
        'decision' => 'deny',
    ]);

    $response->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);
    expect($params['error'])->toBe('access_denied')->and($params['state'])->toBe('xyz');
});

it('never lets a code be redeemed twice', function () {
    $user = staffMember();
    [$verifier, $challenge] = pkcePair();

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'decision' => 'approve',
    ]);
    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $payload = [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
    ];

    $this->postJson('/oauth/token', $payload)->assertOk();
    $this->postJson('/oauth/token', $payload)->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('refuses the wrong code_verifier', function () {
    $user = staffMember();
    [, $challenge] = pkcePair();

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'decision' => 'approve',
    ]);
    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => 'not-the-right-verifier',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('refuses a code redeemed against a different redirect_uri', function () {
    $user = staffMember();
    [$verifier, $challenge] = pkcePair();

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'decision' => 'approve',
    ]);
    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => 'http://127.0.0.1:54219/callback',
        'code_verifier' => $verifier,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('attributes a lead captured through a staff member\'s own OAuth token to that user', function () {
    $user = staffMember(StaffRole::Photographer);
    [$verifier, $challenge] = pkcePair();

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => 'studio-mcp',
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageLeads->value,
        'decision' => 'approve',
    ]);
    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
    ])->assertOk()->json('access_token');

    useSurface(Surface::API);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('Idempotency-Key', 'attributed-lead-1')
        ->postJson('/api/leads', [
            'contact_name' => 'Mara Voss',
            'project_name' => 'Voss — Portrait',
            'type' => 'portrait',
            'source' => 'website',
            'captured_via' => 'other',
            'date' => null,
            'inquiry_text' => 'Interested in a portrait session.',
        ])->assertCreated();

    expect(Project::sole()->lead->captured_by_id)->toBe($user->id);
});

it('an ApiToken caller still carries no captured_by_id, unchanged from I-03', function () {
    $token = App\Integration\Models\ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    useSurface(Surface::API);

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'machine-lead-1')
        ->postJson('/api/leads', [
            'contact_name' => 'Nina Voss',
            'project_name' => 'Voss — Portrait',
            'type' => 'portrait',
            'source' => 'website',
            'captured_via' => 'other',
            'date' => null,
            'inquiry_text' => 'x',
        ])->assertCreated();

    expect(Project::sole()->lead->captured_by_id)->toBeNull();
});

/**
 * N-04's second half: RFC 7591 registration and the two discovery documents,
 * which are what make this flow usable by a client that was never told the
 * well-known id — claude.ai being the one that forced the issue.
 */
function registerClient(array $overrides = []): array
{
    return test()->postJson('/oauth/register', array_merge([
        'client_name' => 'Claude',
        'redirect_uris' => [CLAUDE_REDIRECT_URI],
    ], $overrides))->assertCreated()->json();
}

it('registers a client with no credential and hands back a usable client_id', function () {
    $client = registerClient();

    expect($client['client_id'])->not->toBeEmpty()
        ->and($client['client_name'])->toBe('Claude')
        ->and($client['redirect_uris'])->toBe([CLAUDE_REDIRECT_URI])
        ->and($client['token_endpoint_auth_method'])->toBe('none')
        ->and($client)->not->toHaveKey('client_secret');

    expect(integerIdPaths($client))->toBe([]);
});

it('refuses a redirect_uri that is neither https nor http loopback', function (string $uri) {
    $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])
        ->assertStatus(400)
        ->assertJson(['error' => 'invalid_redirect_uri']);
})->with([
    'http://evil.example/callback',
    'https://evil.example/callback#fragment',
    'not-a-url',
]);

it('refuses to pretend a client has a secret', function () {
    $this->postJson('/oauth/register', [
        'redirect_uris' => [CLAUDE_REDIRECT_URI],
        'token_endpoint_auth_method' => 'client_secret_basic',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_client_metadata']);
});

it('completes the whole flow for a client that registered itself', function () {
    $client = registerClient();
    $user = staffMember(StaffRole::Photographer);
    [$verifier, $challenge] = pkcePair();

    $props = $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'scope' => Capability::ManageContacts->value,
    ]))->assertOk()->viewData('page')['props'];

    // The consent screen names who is asking — with registration open, that
    // is the only thing separating this client from any other.
    expect($props['clientName'])->toBe('Claude')
        ->and($props['redirectHost'])->toBe('claude.ai');

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => $client['client_id'],
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'decision' => 'approve',
    ]);

    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'code' => $params['code'],
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_verifier' => $verifier,
    ])->assertOk()->json();

    expect($token['scope'])->toBe(Capability::ManageContacts->value);
});

it('never lets one client borrow another\'s redirect_uri', function () {
    $client = registerClient();
    $user = staffMember();
    [, $challenge] = pkcePair();

    // The well-known client asking for the registered one's callback, and
    // the reverse. Either would land a code on a stranger's listener.
    $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => 'studio-mcp',
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertStatus(400);

    $this->actingAs($user, 'staff')->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $client['client_id'],
        'redirect_uri' => REDIRECT_URI,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertStatus(400);
});

it('never lets a code be redeemed by a different client than asked for it', function () {
    $client = registerClient();
    $user = staffMember();
    [$verifier, $challenge] = pkcePair();

    $approve = $this->actingAs($user, 'staff')->post('/oauth/authorize', [
        'client_id' => $client['client_id'],
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_challenge' => $challenge,
        'scope' => Capability::ManageContacts->value,
        'decision' => 'approve',
    ]);

    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $params);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => 'studio-mcp',
        'code' => $params['code'],
        'redirect_uri' => CLAUDE_REDIRECT_URI,
        'code_verifier' => $verifier,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

it('advertises the flow to a client that has never seen this server', function () {
    $metadata = $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    expect($metadata['authorization_endpoint'])->toEndWith('/oauth/authorize')
        ->and($metadata['token_endpoint'])->toEndWith('/oauth/token')
        ->and($metadata['registration_endpoint'])->toEndWith('/oauth/register')
        ->and($metadata['code_challenge_methods_supported'])->toBe(['S256'])
        ->and($metadata['token_endpoint_auth_methods_supported'])->toBe(['none'])
        ->and($metadata['scopes_supported'])->toContain(Capability::ManageContacts->value)
        ->and($metadata['issuer'])->toBe(url('/'));
});
