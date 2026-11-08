<?php

use App\Crm\Models\ClientCase;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Models\Thread;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
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

    expect(json_decode(base64_decode(strtr($header, '-_', '+/')), true))->toBe(['alg' => 'HS256', 'typ' => 'at+jwt']);

    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    expect($claims)->toMatchArray(['iss' => 'https://auri-crm.de', 'aud' => 'https://auri-crm.de/.well-known/mercure'])
        ->and($claims['exp'])->toBeGreaterThan(time());

    return $claims['authorization_details'];
}

function liveUpdateGrant(string $action): array
{
    return [[
        'type' => 'https://mercure.rocks/authorization-detail',
        'actions' => [$action],
        'topics' => [['match' => LiveUpdate::topic()]],
    ]];
}

it('publishes to the hub when an entry is audited', function () {
    AuditLogger::record('lead.stage_changed');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://hub/.well-known/mercure'
        && $request['topic'] === LiveUpdate::topic()
        && liveUpdateClaims(substr($request->header('Authorization')[0], 7), 'publisher-key') === liveUpdateGrant('publish'));
});

it('stays silent on a deploy whose hub has no key', function () {
    config(['services.mercure.publisher_key' => null]);
    Exceptions::fake();

    AuditLogger::record('lead.stage_changed');

    Http::assertNothingSent();
    Exceptions::assertNothingReported();
});

it('publishes when mcp adds a note', function () {
    useSurface(Surface::API);
    $token = ApiToken::factory()->withScopes(Capability::ManageNotes)->create();
    $project = ClientCase::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'note-1')
        ->postJson("/api/cases/{$project->public_id}/notes", ['body' => 'Gegenlicht ab 20 Uhr.'])
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
    config(['session.secure' => true]);

    $response = $this->actingAs(User::factory()->create(), 'staff')->get('/');

    $response->assertOk();
    expect(liveUpdateClaims($response->getCookie('__Secure-mercure_access_token', false)->getValue(), 'subscriber-key'))
        ->toBe(liveUpdateGrant('subscribe'));
});

it('drops the cookie prefix on plain http', function () {
    config(['session.secure' => false]);

    $this->actingAs(User::factory()->create(), 'staff')->get('/')
        ->assertCookie('mercure_access_token', encrypted: false)
        ->assertCookieMissing('__Secure-mercure_access_token');
});

it('publishes a staff reply to the contacts on the thread, on the portal hub', function () {
    config(['services.mercure.portal_url' => 'http://portal-hub/.well-known/mercure']);
    $project = bookedContactProject();
    $contact = $project->contacts()->sole();
    $thread = Thread::factory()->for($project, 'case')->create();
    $thread->participants()->create(['contact_id' => $contact->id]);

    app(PostMessage::class)($thread, staffMember(StaffRole::Admin), 'Bis Samstag.');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://portal-hub/.well-known/mercure'
        && $request['topic'] === LiveUpdate::contactTopic($contact->public_id)
        && liveUpdateClaims(substr($request->header('Authorization')[0], 7), 'publisher-key')[0]['topics'] === [['match' => $request['topic']]]);
});
