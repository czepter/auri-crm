<?php

use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.mercure.url' => 'http://hub/.well-known/mercure',
        'services.mercure.publisher_key' => 'publisher-key',
        'services.mercure.subscriber_key' => 'subscriber-key',
    ]);

    Http::fake();
});

function liveUpdateClaims(string $jwt, string $key): array
{
    [$header, $payload, $signature] = explode('.', $jwt);

    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $key, true)), '+/', '-_'), '=');
    expect(hash_equals($expected, $signature))->toBeTrue();

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['mercure'];
}

it('publishes to the hub when an entry is audited', function () {
    AuditLogger::record('lead.stage_changed');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://hub/.well-known/mercure'
        && $request['topic'] === LiveUpdate::topic()
        && liveUpdateClaims(substr($request->header('Authorization')[0], 7), 'publisher-key') === ['publish' => [LiveUpdate::topic()]]);
});

it('publishes when mcp adds a note', function () {
    useSurface(Surface::API);
    $token = ApiToken::factory()->withScopes(Capability::ManageNotes)->create();
    $project = Project::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'note-1')
        ->postJson("/api/projects/{$project->public_id}/notes", ['body' => 'Gegenlicht ab 20 Uhr.'])
        ->assertCreated();

    Http::assertSentCount(1);
});

it('does not publish for an mcp read', function () {
    useSurface(Surface::API);
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->getJson('/api/contacts')
        ->assertOk();

    Http::assertNothingSent();
});

it('hands signed-in staff a subscriber cookie for the topic', function () {
    $response = $this->actingAs(User::factory()->create(), 'staff')->get('/einrichtung/studio');

    $response->assertOk();
    expect(liveUpdateClaims($response->getCookie('mercureAuthorization', false)->getValue(), 'subscriber-key'))
        ->toBe(['subscribe' => [LiveUpdate::topic()]]);
});
