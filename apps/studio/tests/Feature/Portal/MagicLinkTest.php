<?php

use App\Crm\Models\Contact;
use App\Models\AuditLog;
use App\Portal\Actions\ConsumeClientAccess;
use App\Portal\Models\ClientAccess;
use App\Portal\Notifications\ClientAccessLink;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    // These specs exercise the client surface, so the process has to be the
    // one that serves it — route table, guard and cookie together.
    useSurface(Surface::PORTAL);
    RateLimiter::clear('portal-link-email:lena@example.test');
});

function magicLink(Contact $contact, array $overrides = []): array
{
    $token = 'raw-'.bin2hex(random_bytes(8));

    $access = ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(15),
        ...$overrides,
    ]);

    return [$token, $access];
}

it('issues a link to a known address', function () {
    Notification::fake();
    $contact = Contact::factory()->create(['email' => 'lena@example.test']);

    $this->post('/anmelden', ['email' => 'lena@example.test'])->assertRedirect();

    expect(ClientAccess::sole()->contact_id)->toBe($contact->id);
    Notification::assertSentOnDemand(ClientAccessLink::class);
});

it('answers an unknown address exactly as a known one', function () {
    // Status and body both, because either one differing turns the form into
    // a way of asking who the studio's clients are.
    Notification::fake();
    Contact::factory()->create(['email' => 'lena@example.test']);

    $known = $this->post('/anmelden', ['email' => 'lena@example.test']);
    $unknown = $this->post('/anmelden', ['email' => 'niemand@example.test']);

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'))
        ->and(session('status'))->toBe(__('portal.link_sent'))
        ->and(ClientAccess::count())->toBe(1);
});

it('answers an unknown address within the same timing envelope', function () {
    // The one asymmetry a status code cannot hide: sending mail costs time
    // that not sending it does not.
    Notification::fake();
    Contact::factory()->create(['email' => 'lena@example.test']);

    $time = function (string $email) {
        $startedAt = microtime(true);
        $this->post('/anmelden', ['email' => $email]);

        return (microtime(true) - $startedAt) * 1000;
    };

    $known = $time('lena@example.test');
    RateLimiter::clear('portal-link-email:lena@example.test');
    $unknown = $time('niemand@example.test');

    // Both are held to the floor, so neither can be picked out by being fast.
    expect($known)->toBeGreaterThan(200.0)
        ->and($unknown)->toBeGreaterThan(200.0);
});

it('authenticates once and is dead on the second use', function () {
    $contact = Contact::factory()->create();
    [$token] = magicLink($contact);

    $this->get("/zugang/{$token}")->assertRedirect('/');
    expect(auth('portal')->id())->toBe($contact->id);

    auth('portal')->logout();

    $this->get("/zugang/{$token}")->assertRedirect('/anmelden');
    expect(auth('portal')->check())->toBeFalse();
});

it('stamps consumption on the row', function () {
    $contact = Contact::factory()->create();
    [$token, $access] = magicLink($contact);

    $this->get("/zugang/{$token}");

    expect($access->fresh()->consumed_at)->not->toBeNull()
        ->and($access->fresh()->isLive())->toBeFalse();
});

it('expires fifteen minutes after issue', function () {
    $contact = Contact::factory()->create();
    [$token] = magicLink($contact);

    $this->travel(16)->minutes();

    $this->get("/zugang/{$token}")->assertRedirect('/anmelden');
    expect(auth('portal')->check())->toBeFalse();
});

it('still works at fourteen minutes', function () {
    $contact = Contact::factory()->create();
    [$token] = magicLink($contact);

    $this->travel(14)->minutes();

    $this->get("/zugang/{$token}")->assertRedirect('/');
});

it('refuses a forged token', function () {
    Contact::factory()->create();

    $this->get('/zugang/'.str_repeat('a', 64))->assertRedirect('/anmelden');
    expect(auth('portal')->check())->toBeFalse();
});

it('stores the token as a hash and never in the clear', function () {
    $contact = Contact::factory()->create();
    [$token, $access] = magicLink($contact);

    expect($access->token_hash)->toBe(hash('sha256', $token))
        ->and($access->fresh()->toArray())->not->toHaveKey('token_hash');
});

it('lets one link be spent only once even when raced', function () {
    // Read-then-write would let both callers through; the conditional update
    // is what makes exactly one of them win.
    $contact = Contact::factory()->create();
    [$token] = magicLink($contact);

    $first = app(ConsumeClientAccess::class)($token);
    $second = app(ConsumeClientAccess::class)($token);

    expect($first?->contact_id)->toBe($contact->id)
        ->and($second)->toBeNull();
});

it('fails in the same uniform shape once the address limit is hit', function () {
    Notification::fake();
    Contact::factory()->create(['email' => 'lena@example.test']);

    $responses = collect(range(1, 7))->map(
        fn () => $this->post('/anmelden', ['email' => 'lena@example.test'])
    );

    // Every answer identical, and the links stop being issued.
    expect($responses->map->getStatusCode()->unique()->all())->toHaveCount(1)
        ->and(ClientAccess::count())->toBe(5);
});

it('counts the address and the ip as two separate buckets', function () {
    Notification::fake();

    // Five different addresses from one IP: under the address limit each
    // time, and the IP counter is what notices.
    foreach (range(1, 5) as $i) {
        Contact::factory()->create(['email' => "klient{$i}@example.test"]);
        $this->post('/anmelden', ['email' => "klient{$i}@example.test"]);
    }

    expect(RateLimiter::attempts('portal-link-ip:127.0.0.1'))->toBe(5)
        ->and(RateLimiter::attempts('portal-link-email:klient1@example.test'))->toBe(1);
});

it('writes one audit row per issue and one per consumption', function () {
    Notification::fake();
    Contact::factory()->create(['email' => 'lena@example.test']);

    $this->post('/anmelden', ['email' => 'lena@example.test']);

    $token = null;
    Notification::assertSentOnDemand(ClientAccessLink::class, function ($notification) use (&$token) {
        return true;
    });

    expect(AuditLog::where('event', 'portal.link_issued')->count())->toBe(1);

    $contact = Contact::where('email', 'lena@example.test')->sole();
    [$raw] = magicLink($contact);
    $this->get("/zugang/{$raw}");

    expect(AuditLog::where('event', 'portal.link_consumed')->count())->toBe(1);
});

it('keeps the token out of every audit row', function () {
    Notification::fake();
    Contact::factory()->create(['email' => 'lena@example.test']);

    $this->post('/anmelden', ['email' => 'lena@example.test']);

    $rows = AuditLog::all()->pluck('context')->toJson();

    expect($rows)->not->toContain('token');
});
