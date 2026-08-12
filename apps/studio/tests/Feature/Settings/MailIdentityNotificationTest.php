<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvited;
use App\Portal\Notifications\ClientAccessLink;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

/**
 * A StaffInvitation with a real inviter — StaffInvited::toMail() reads
 * invitedBy->name. Named t06Invitation rather than the generic invite():
 * tests/Feature/Staff/InvitationTest.php already declares invite() at file
 * scope, and a global function declared in one test file is only visible to
 * another if Pest happens to load them in the right order (see
 * tests/Pest.php's own note on this).
 */
function t06Invitation(): StaffInvitation
{
    return StaffInvitation::create([
        'email' => 'nina@nordlicht.test',
        'name' => 'Nina Sturm',
        'role' => StaffRole::SecondShooter,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => User::factory()->create()->id,
    ])->fresh();
}

it("leaves ClientAccessLink's mail under the tenant's own address, sourced live at send time with no deploy", function () {
    $owner = staffMember(StaffRole::Owner);
    $contact = Contact::factory()->create();

    // First send, before any tenant override — the env default.
    config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Example']);
    $first = (new ClientAccessLink($contact, 'https://example.test/zugang/one', new StudioSettingsResolver))
        ->toMail($contact);
    expect($first->from)->toBe(['hello@example.com', 'Example']);

    // The setting changes — no deploy, no cache-clear command, exactly the
    // write StudioSettingsResolver::set() already busts the shared cache
    // for.
    (new StudioSettingsResolver)->set('mail_from_address', 'studio@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_from_name', 'Atelier Nordlicht', $owner);

    // Second send, through a completely fresh resolver instance — the same
    // shape a new request or a queued job would build.
    $second = (new ClientAccessLink($contact, 'https://example.test/zugang/two', new StudioSettingsResolver))
        ->toMail($contact);

    expect($second->from)->toBe(['studio@nordlicht.test', 'Atelier Nordlicht'])
        ->and($second->from)->not->toBe($first->from);
});

it('sets reply-to and BCC on ClientAccessLink only when the tenant configured them', function () {
    $owner = staffMember(StaffRole::Owner);
    $contact = Contact::factory()->create();

    $bare = (new ClientAccessLink($contact, 'https://example.test/zugang', new StudioSettingsResolver))->toMail($contact);
    expect($bare->replyTo)->toBe([])
        ->and($bare->bcc)->toBe([]);

    (new StudioSettingsResolver)->set('mail_reply_to', 'buero@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_bcc', 'ablage@nordlicht.test', $owner);

    $configured = (new ClientAccessLink($contact, 'https://example.test/zugang', new StudioSettingsResolver))->toMail($contact);
    expect($configured->replyTo)->toBe([['buero@nordlicht.test', null]])
        ->and($configured->bcc)->toBe([['ablage@nordlicht.test', null]]);
});

it("appends the studio's signature block as its own line on ClientAccessLink", function () {
    $owner = staffMember(StaffRole::Owner);
    $contact = Contact::factory()->create();
    (new StudioSettingsResolver)->set('mail_signature', 'Herzliche Grüße, das Team von Atelier Nordlicht', $owner);

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang', new StudioSettingsResolver))->toMail($contact);

    expect($message->outroLines)->toContain('Herzliche Grüße, das Team von Atelier Nordlicht');
});

it(
    "carries the same legal particulars in ClientAccessLink's footer that RenderContractJob's studio.* merge fields read, from one source",
    function () {
        $owner = staffMember(StaffRole::Owner);
        $contact = Contact::factory()->create();
        $settings = new StudioSettingsResolver;
        $settings->set('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
        $settings->set('street', 'Elbchaussee 112', $owner);
        $settings->set('postcode', '22763', $owner);
        $settings->set('city', 'Hamburg', $owner);
        $settings->set('tax_number', '22/123/45678', $owner);
        $settings->set('vat_id', 'DE284957312', $owner);

        // The one source both this mail footer and RenderContractJob's
        // mergeData() read from — see App\Concerns\AppliesStudioMailIdentity.
        $studio = (new StudioSettingsResolver)->attributes();

        $message = (new ClientAccessLink($contact, 'https://example.test/zugang', new StudioSettingsResolver))->toMail($contact);
        $footer = collect($message->outroLines)->last();

        expect($footer)->toContain($studio['legal_name'])
            ->toContain($studio['street'])
            ->toContain($studio['postcode'])
            ->toContain($studio['city'])
            ->toContain($studio['tax_number'])
            ->toContain($studio['vat_id']);
    }
);

it('omits the footer line entirely when the studio has entered no legal particulars at all', function () {
    $contact = Contact::factory()->create();

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang', new StudioSettingsResolver))->toMail($contact);

    // No signature, no legal particulars — the outro carries only the
    // notification's own body lines, no blank trailing line.
    expect($message->outroLines)->toBe([
        'Der Link gilt 15 Minuten und funktioniert einmal.',
        'Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.',
    ]);
});

it("leaves StaffInvited's mail under the tenant's own address, resolved live at send time", function () {
    $owner = staffMember(StaffRole::Owner);
    $invitation = t06Invitation();

    $first = (new StaffInvited($invitation, 'https://example.test/einladung/raw', new StudioSettingsResolver))
        ->toMail($invitation);

    (new StudioSettingsResolver)->set('mail_from_address', 'studio@nordlicht.test', $owner);
    (new StudioSettingsResolver)->set('mail_from_name', 'Atelier Nordlicht', $owner);

    $second = (new StaffInvited($invitation, 'https://example.test/einladung/raw', new StudioSettingsResolver))
        ->toMail($invitation);

    expect($second->from)->toBe(['studio@nordlicht.test', 'Atelier Nordlicht'])
        ->and($second->from)->not->toBe($first->from);
});

it(
    "carries the same legal particulars in StaffInvited's footer that RenderContractJob's studio.* merge fields read",
    function () {
        $owner = staffMember(StaffRole::Owner);
        $invitation = t06Invitation();
        (new StudioSettingsResolver)->set('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
        (new StudioSettingsResolver)->set('city', 'Hamburg', $owner);

        $studio = (new StudioSettingsResolver)->attributes();

        $message = (new StaffInvited($invitation, 'https://example.test/einladung/raw', new StudioSettingsResolver))
            ->toMail($invitation);
        $footer = collect($message->outroLines)->last();

        expect($footer)->toContain($studio['legal_name'])
            ->toContain($studio['city']);
    }
);
