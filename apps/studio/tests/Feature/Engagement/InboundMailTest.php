<?php

use App\Engagement\Actions\IngestInboundMessage;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Thread;
use App\Engagement\Support\InboundAddress;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Actions\RotateInboundSecret;
use App\Settings\Models\StudioSettings;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

const INBOUND_SIGNING_SECRET = 'webhook-secret';

// inboundDomain(), inboundThread() and withSecret() live in tests/Pest.php —
// two suites need them, and a global declared in one test file collides the
// moment a second declares it.

beforeEach(function () {
    config()->set('mail.inbound.domain', inboundDomain());
    config()->set('mail.inbound.signing_secret', INBOUND_SIGNING_SECRET);
    Notification::fake();
});

function deliver(array $overrides = []): InboundMessage
{
    return app(IngestInboundMessage::class)(array_merge([
        'message_id' => '<client-1@mail.example>',
        'from' => 'lena@example.test',
        'to' => 'catchall@'.inboundDomain(),
        'subject' => 'Re: Ihre Hochzeit',
        'body' => 'Klingt gut!',
        'dmarc' => 'pass',
    ], $overrides));
}

// --- the addresses ------------------------------------------------------

it('mints an unguessable thread address and reads its thread back', function () {
    $secret = 'a-tenant-secret';
    $thread = inboundThread();

    $address = InboundAddress::forThread($thread, $secret);

    expect($address)->toContain('t.'.$thread->public_id.'.')
        ->and($address)->toEndWith('@'.inboundDomain())
        ->and(InboundAddress::threadIdFrom($address, $secret))->toBe($thread->public_id);
});

it('refuses an address whose HMAC was forged', function () {
    $thread = inboundThread();

    // The shape is right and the public_id is real — only the signature is
    // invented, which is exactly the attack the HMAC exists to stop.
    $forged = 't.'.$thread->public_id.'.0000000000000000@'.inboundDomain();

    expect(InboundAddress::threadIdFrom($forged, 'a-tenant-secret'))->toBeNull();
});

it('makes every previously issued address fail once the secret rotates', function () {
    $thread = inboundThread();
    $address = InboundAddress::forThread($thread, 'old-secret');

    expect(InboundAddress::threadIdFrom($address, 'old-secret'))->toBe($thread->public_id)
        ->and(InboundAddress::threadIdFrom($address, 'new-secret'))->toBeNull();
});

// --- ingest -------------------------------------------------------------

it('threads a reply from a participant sent to the thread address', function () {
    $secret = withSecret();
    $thread = inboundThread();

    $row = deliver(['to' => InboundAddress::forThread($thread, $secret)]);

    expect($row->state)->toBe(InboundMessageState::Threaded)
        ->and($thread->messages()->count())->toBe(1)
        ->and($thread->messages()->sole()->body)->toBe('Klingt gut!')
        ->and($thread->messages()->sole()->contact_id)->not->toBeNull()
        ->and($thread->messages()->sole()->author_id)->toBeNull();
});

it('never attaches on a From: address alone', function () {
    $secret = withSecret();
    $thread = inboundThread();

    // A participant's address, but sent to the catch-all rather than to a
    // signed thread address: no verified claim about which conversation it
    // belongs to, so a human decides.
    $row = deliver(['to' => 'hallo.abc@'.inboundDomain()]);

    expect($row->state)->toBe(InboundMessageState::Triage)
        ->and($row->reason)->toBe('unrecognised_address')
        ->and($thread->messages()->count())->toBe(0);
});

it('refuses a stranger who reached a real thread address', function () {
    $secret = withSecret();
    $thread = inboundThread();

    $row = deliver([
        'to' => InboundAddress::forThread($thread, $secret),
        'from' => 'fremder@example.test',
    ]);

    expect($row->state)->toBe(InboundMessageState::Triage)
        ->and($row->reason)->toBe('sender_not_participant')
        ->and($thread->messages()->count())->toBe(0);
});

it('sends a rotated-away address to triage rather than trusting it', function () {
    withSecret();
    $thread = inboundThread();
    $stale = InboundAddress::forThread($thread, 'the-previous-secret');

    $row = deliver(['to' => $stale]);

    expect($row->state)->toBe(InboundMessageState::Triage)
        ->and($thread->messages()->count())->toBe(0);
});

it('quarantines a DMARC failure, visibly, rather than dropping it', function () {
    $secret = withSecret();
    $thread = inboundThread();

    $row = deliver(['to' => InboundAddress::forThread($thread, $secret), 'dmarc' => 'fail']);

    expect($row->state)->toBe(InboundMessageState::Quarantined)
        ->and($row->reason)->toBe('dmarc_fail')
        ->and(InboundMessage::count())->toBe(1)
        ->and($thread->messages()->count())->toBe(0);
});

it('creates one message when the same delivery is replayed', function () {
    $secret = withSecret();
    $thread = inboundThread();
    $to = InboundAddress::forThread($thread, $secret);

    deliver(['to' => $to]);
    deliver(['to' => $to]);

    expect(InboundMessage::count())->toBe(1)
        ->and($thread->messages()->count())->toBe(1);
});

it('keeps a row for every delivery, whatever became of it', function () {
    withSecret();
    inboundThread();

    deliver(['message_id' => '<a@x>', 'to' => 'was.auch.immer@'.inboundDomain()]);
    deliver(['message_id' => '<b@x>', 'dmarc' => 'fail']);

    expect(InboundMessage::count())->toBe(2);
});

// --- the webhook --------------------------------------------------------

it('verifies the signature before it parses anything', function () {
    useSurface(Surface::API);

    $this->postJson('/api/mail/eingang', ['from' => 'x@example.test'])->assertStatus(401);

    expect(InboundMessage::count())->toBe(0);
});

it('accepts a signed delivery', function () {
    useSurface(Surface::API);
    withSecret();
    $thread = inboundThread();

    $payload = json_encode([
        'message_id' => '<signed@mail.example>',
        'from' => 'lena@example.test',
        'to' => InboundAddress::forThread($thread, 'a-tenant-secret'),
        'body' => 'Danke!',
        'dmarc' => 'pass',
    ]);

    $this->call(
        'POST',
        '/api/mail/eingang',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_INBOUND_SIGNATURE' => hash_hmac('sha256', $payload, INBOUND_SIGNING_SECRET),
        ],
        content: $payload,
    )->assertStatus(202);

    expect($thread->messages()->sole()->body)->toBe('Danke!');
});

it('refuses everything when no signing secret is configured', function () {
    useSurface(Surface::API);
    config()->set('mail.inbound.signing_secret', null);

    $this->postJson('/api/mail/eingang', ['from' => 'x@example.test'])->assertStatus(401);
});

// --- rotation is the Inhaber's -----------------------------------------

it('lets the Inhaber rotate the secret, and records it', function () {
    $secret = app(RotateInboundSecret::class)(staffMember(StaffRole::Owner));

    expect($secret)->toHaveLength(48)
        ->and(StudioSettings::query()->firstOrFail()->inbound_secret)->toBe($secret)
        ->and(AuditLog::where('event', 'settings.inbound_secret_rotated')->count())->toBe(1);
});

it('refuses rotation in the action, not merely on the screen', function () {
    app(RotateInboundSecret::class)(staffMember(StaffRole::Photographer));
})->throws(HttpException::class);

it('never writes the secret into the audit row', function () {
    $secret = app(RotateInboundSecret::class)(staffMember(StaffRole::Owner));

    $row = AuditLog::where('event', 'settings.inbound_secret_rotated')->sole();

    expect(json_encode($row->context))->not->toContain($secret);
});

it('holds no mailbox credential anywhere in the schema', function () {
    // The claim the whole story rests on, asserted rather than trusted.
    $columns = collect(DB::select("
        select table_name, column_name from information_schema.columns
        where table_schema = 'public'
    "))->map(fn ($row) => $row->table_name.'.'.$row->column_name);

    $suspicious = $columns->filter(fn (string $column) => str_contains($column, 'imap')
        || str_contains($column, 'mailbox_password')
        || str_contains($column, 'mailbox_token')
        || str_contains($column, 'oauth_mailbox'));

    expect($suspicious->all())->toBe([]);
});

// --- the catch-all carries the studio's own name ------------------------

it('puts the studio name in the catch-all, slugged', function () {
    expect(InboundAddress::catchAll('Atelier Nordlicht Fotografie GmbH', 'a-tenant-secret'))
        ->toStartWith('atelier-nordlicht-fotografie-gmbh.')
        ->toEndWith('@'.inboundDomain());
});

it('keeps the secret half unguessable, so the readable handle is not the credential', function () {
    $first = InboundAddress::catchAll('Atelier Nordlicht', 'one-secret');
    $second = InboundAddress::catchAll('Atelier Nordlicht', 'another-secret');

    // Same studio, same handle, different address: knowing the studio's name
    // does not let anyone address it.
    expect($first)->not->toBe($second)
        ->and($first)->toStartWith('atelier-nordlicht.')
        ->and($second)->toStartWith('atelier-nordlicht.');
});

it('falls back rather than minting an address with an empty handle', function () {
    expect(InboundAddress::handle(''))->toBe('studio')
        ->and(InboundAddress::handle('日本語'))->not->toBe('');
});

it('shows the studio its own catch-all on the Posteingang screen', function () {
    withSecret();
    StudioSettings::query()->firstOrFail()->forceFill(['display_name' => 'Atelier Nordlicht'])->save();
    app()->forgetInstance(StudioSettingsResolver::class);

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/posteingang')
        ->assertOk()
        ->assertInertia(function ($page) {
            expect($page->toArray()['props']['catchAll'])->toStartWith('atelier-nordlicht.');
        });
});
