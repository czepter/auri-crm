<?php

use App\Engagement\Actions\IngestInboundMessage;
use App\Engagement\Enums\InboundMessageState;
use App\Engagement\Support\InboundAddress;
use App\Engagement\Support\InboundMimeMessage;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// inboundThread(), withSecret() and inboundDomain() live in tests/Pest.php.

beforeEach(function () {
    config()->set('mail.inbound.domain', inboundDomain());
    config()->set('mail.inbound.authserv_id', null);
    Notification::fake();
});

/**
 * A raw message as an MTA would hand it over. CRLF throughout, because that
 * is what arrives on the wire and a parser tested only against LF is a
 * parser that works everywhere except production.
 */
function rawMail(array $headers = [], string $body = "Klingt gut!\r\n\r\nViele Grüße\r\nLena"): string
{
    $headers = array_merge([
        'Delivered-To' => 'catchall@'.inboundDomain(),
        'Authentication-Results' => 'mx.'.inboundDomain().'; spf=pass smtp.mailfrom=example.test; dkim=pass header.d=example.test; dmarc=pass header.from=example.test',
        'Message-ID' => '<abc-123@mail.example>',
        'From' => 'Lena Meier <lena@example.test>',
        'To' => 'hallo@'.inboundDomain(),
        'Subject' => 'Re: Ihre Hochzeit',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ], $headers);

    $lines = [];
    foreach ($headers as $name => $value) {
        if ($value !== null) {
            $lines[] = "{$name}: {$value}";
        }
    }

    return implode("\r\n", $lines)."\r\n\r\n".$body;
}

it('flattens a raw message into what the ingest takes', function () {
    $parsed = InboundMimeMessage::fromRaw(rawMail());

    expect($parsed['message_id'])->toBe('<abc-123@mail.example>')
        ->and($parsed['from'])->toBe('lena@example.test')
        // Delivered-To wins over To:, because it names where the delivery
        // actually landed — which for a `t.` reply address is the point.
        ->and($parsed['to'])->toBe('catchall@'.inboundDomain())
        ->and($parsed['subject'])->toBe('Re: Ihre Hochzeit')
        ->and($parsed['body'])->toContain('Klingt gut!')
        ->and($parsed['spf'])->toBe('pass')
        ->and($parsed['dkim'])->toBe('pass')
        ->and($parsed['dmarc'])->toBe('pass');
});

it('falls back to To: when nothing recorded the delivery address', function () {
    $parsed = InboundMimeMessage::fromRaw(rawMail(['Delivered-To' => null]));

    expect($parsed['to'])->toBe('hallo@'.inboundDomain());
});

it('reads a folded header as one value', function () {
    // Mail clients fold long headers at whatever width they like; a parser
    // that splits on newlines alone reads half a subject.
    $raw = rawMail(['Subject' => "Re: Ihre Hochzeit im\r\n Juni am Strand"]);

    expect(InboundMimeMessage::fromRaw($raw)['subject'])->toBe('Re: Ihre Hochzeit im Juni am Strand');
});

it('takes the topmost Authentication-Results and ignores one the sender forged below it', function () {
    // The sender put a pass-everything header in the message they sent. Ours
    // was prepended by the receiving MTA, so it is the one above theirs.
    $raw = 'Delivered-To: catchall@'.inboundDomain()."\r\n"
        .'Authentication-Results: mx.'.inboundDomain()."; spf=fail; dkim=fail; dmarc=fail\r\n"
        ."Authentication-Results: totally.legit; spf=pass; dkim=pass; dmarc=pass\r\n"
        ."Message-ID: <forged@mail.example>\r\n"
        ."From: boese@example.test\r\n"
        ."Subject: Hallo\r\n\r\nText";

    $parsed = InboundMimeMessage::fromRaw($raw);

    expect($parsed['dmarc'])->toBe('fail')
        ->and($parsed['spf'])->toBe('fail');
});

it('trusts no verdict at all when the topmost header is not from the configured MTA', function () {
    config()->set('mail.inbound.authserv_id', 'mx.'.inboundDomain());

    $raw = rawMail(['Authentication-Results' => 'someone.else; spf=pass; dkim=pass; dmarc=pass']);
    $parsed = InboundMimeMessage::fromRaw($raw);

    // Null rather than pass: an unverifiable claim is not a passing check.
    expect($parsed['dmarc'])->toBeNull()
        ->and($parsed['spf'])->toBeNull();
});

it('takes the text part rather than the HTML one from a multipart message', function () {
    $raw = 'Delivered-To: catchall@'.inboundDomain()."\r\n"
        ."Message-ID: <multi@mail.example>\r\n"
        ."From: lena@example.test\r\n"
        ."Subject: Mehrteilig\r\n"
        ."MIME-Version: 1.0\r\n"
        ."Content-Type: multipart/alternative; boundary=\"grenze\"\r\n\r\n"
        ."--grenze\r\n"
        ."Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        ."Nur der Text bitte.\r\n"
        ."--grenze\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\n\r\n"
        ."<p>Nur der Text bitte.</p>\r\n"
        ."--grenze--\r\n";

    $parsed = InboundMimeMessage::fromRaw($raw);

    expect($parsed['body'])->toContain('Nur der Text bitte.')
        ->and($parsed['body'])->not->toContain('<p>');
});

it('decodes quoted-printable so umlauts survive the trip', function () {
    $raw = 'Delivered-To: catchall@'.inboundDomain()."\r\n"
        ."Message-ID: <qp@mail.example>\r\n"
        ."From: lena@example.test\r\n"
        ."Subject: Gr=?utf-8?Q?=C3=BC?=sse\r\n"
        ."Content-Type: text/plain; charset=UTF-8\r\n"
        ."Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        .'Sch=C3=B6ne Gr=C3=BC=C3=9Fe aus M=C3=BCnchen';

    expect(InboundMimeMessage::fromRaw($raw)['body'])->toContain('Schöne Grüße aus München');
});

// --- end to end, through the real routing -------------------------------

it('threads a real raw reply sent to a thread address', function () {
    $secret = withSecret();
    $thread = inboundThread();

    $raw = rawMail(['Delivered-To' => InboundAddress::forThread($thread, $secret)]);

    $row = app(IngestInboundMessage::class)(InboundMimeMessage::fromRaw($raw));

    expect($row->state)->toBe(InboundMessageState::Threaded)
        ->and($thread->messages()->sole()->body)->toContain('Klingt gut!');
});

it('quarantines a raw message whose DMARC our own MTA failed', function () {
    $secret = withSecret();
    $thread = inboundThread();

    $raw = rawMail([
        'Delivered-To' => InboundAddress::forThread($thread, $secret),
        'Authentication-Results' => 'mx.'.inboundDomain().'; spf=fail; dkim=fail; dmarc=fail',
    ]);

    $row = app(IngestInboundMessage::class)(InboundMimeMessage::fromRaw($raw));

    expect($row->state)->toBe(InboundMessageState::Quarantined)
        ->and($thread->messages()->count())->toBe(0);
});

it('ingests the same raw message twice as one', function () {
    $secret = withSecret();
    $thread = inboundThread();
    $raw = rawMail(['Delivered-To' => InboundAddress::forThread($thread, $secret)]);

    app(IngestInboundMessage::class)(InboundMimeMessage::fromRaw($raw));
    app(IngestInboundMessage::class)(InboundMimeMessage::fromRaw($raw));

    expect($thread->messages()->count())->toBe(1);
});

// --- the command --------------------------------------------------------

it('does nothing, loudly enough, when no mailbox is configured', function () {
    config()->set('mail.inbound.imap.host', null);

    $this->artisan('mail:poll-inbound')
        ->expectsOutputToContain('No inbound mailbox configured')
        ->assertSuccessful();
});

it('holds no studio mailbox credential — the one it uses is this app\'s own', function () {
    // The claim the design rests on: the IMAP credentials are configuration
    // for a mailbox this deployment owns, never a column a studio fills in.
    $columns = collect(DB::select("
        select column_name from information_schema.columns where table_schema = 'public'
    "))->pluck('column_name');

    expect($columns->filter(fn ($c) => str_contains($c, 'imap'))->all())->toBe([]);
    expect(StudioSettings::query()->firstOrFail()->getAttributes())->not->toHaveKey('imap_password');
});
