<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\StaffInvitation;
use App\Models\StudioSettings;
use App\Models\User;
use App\Portal\Notifications\ClientAccessLink;
use App\Settings\Actions\UpdateStudioSetting;
use App\Staff\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

// The footer lives in the layout's own scope, filled by AppServiceProvider's
// `mail::layout` composer — Blade hands a view's data to no nested component,
// so it can only be asserted on the rendered document, never on the
// MailMessage. See App\Notifications\Support\StudioMailFooter.
function renderedMail(MailMessage $message): string
{
    return (string) app(Markdown::class)
        ->render($message->view ?? $message->markdown, $message->data());
}

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
        'role' => StaffRole::Contributor,
        'token_hash' => StaffInvitation::hash('raw-token'),
        'expires_at' => now()->addDays(7),
        'invited_by_id' => User::factory()->create()->id,
    ])->fresh();
}

it("leaves ClientAccessLink's mail under the tenant's own address, sourced live at send time with no deploy", function () {
    $owner = staffMember(StaffRole::Admin);
    $contact = Contact::factory()->create();

    // First send, before any tenant override — the env default.
    config(['mail.from.address' => 'hello@example.com', 'mail.from.name' => 'Example']);
    $first = (new ClientAccessLink($contact, 'https://example.test/zugang/one'))
        ->toMail($contact);
    expect($first->from)->toBe(['hello@example.com', 'Example']);

    // The setting changes — no deploy, no cache-clear command, exactly the
    // write App\Settings\Actions\UpdateStudioSetting already busts the shared cache
    // for.
    app(UpdateStudioSetting::class)('mail_from_address', 'studio@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_from_name', 'Atelier Nordlicht', $owner);

    // Second send, through a completely fresh resolver instance — the same
    // shape a new request or a queued job would build.
    $second = (new ClientAccessLink($contact, 'https://example.test/zugang/two'))
        ->toMail($contact);

    expect($second->from)->toBe(['studio@nordlicht.test', 'Atelier Nordlicht'])
        ->and($second->from)->not->toBe($first->from);
});

it('sets reply-to and BCC on ClientAccessLink only when the tenant configured them', function () {
    $owner = staffMember(StaffRole::Admin);
    $contact = Contact::factory()->create();

    $bare = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);
    expect($bare->replyTo)->toBe([])
        ->and($bare->bcc)->toBe([]);

    app(UpdateStudioSetting::class)('mail_reply_to', 'buero@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_bcc', 'ablage@nordlicht.test', $owner);

    $configured = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);
    expect($configured->replyTo)->toBe([['buero@nordlicht.test', null]])
        ->and($configured->bcc)->toBe([['ablage@nordlicht.test', null]]);
});

it("appends the studio's signature block as its own line on ClientAccessLink", function () {
    $owner = staffMember(StaffRole::Admin);
    $contact = Contact::factory()->create();
    app(UpdateStudioSetting::class)('mail_signature', 'Herzliche Grüße, das Team von Atelier Nordlicht', $owner);

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);

    expect(renderedMail($message))->toContain('Herzliche Grüße, das Team von Atelier Nordlicht');
});

it(
    "carries the same legal particulars in ClientAccessLink's footer that the studio.* merge fields read, from one source",
    function () {
        $owner = staffMember(StaffRole::Admin);
        $contact = Contact::factory()->create();
        app(UpdateStudioSetting::class)('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
        app(UpdateStudioSetting::class)('street', 'Elbchaussee 112', $owner);
        app(UpdateStudioSetting::class)('postcode', '22763', $owner);
        app(UpdateStudioSetting::class)('city', 'Hamburg', $owner);
        app(UpdateStudioSetting::class)('tax_number', '22/123/45678', $owner);
        app(UpdateStudioSetting::class)('vat_id', 'DE284957312', $owner);

        // The one source both this mail footer and the
        // mergeData() read from — see App\Concerns\AppliesStudioMailIdentity.
        $studio = StudioSettings::current()->profile();

        $message = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);
        expect(renderedMail($message))->toContain($studio['legal_name'])
            ->toContain($studio['street'])
            ->toContain($studio['postcode'])
            ->toContain($studio['city'])
            ->toContain($studio['tax_number'])
            ->toContain($studio['vat_id']);
    }
);

it('omits the footer line entirely when the studio has entered no legal particulars at all', function () {
    $contact = Contact::factory()->create();

    $message = (new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact);

    // No signature, no legal particulars — the composer hands the layout a
    // null footer, so the whole ruled band is skipped rather than drawn empty.
    expect(renderedMail($message))->not->toContain('St.-Nr.')
        ->and($message->outroLines)->toBe([
            'Der Link gilt 15 Minuten und funktioniert einmal.',
            'Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.',
        ]);
});

it("leaves StaffInvited's mail under the tenant's own address, resolved live at send time", function () {
    $owner = staffMember(StaffRole::Admin);
    $invitation = t06Invitation();

    $first = (new StaffInvited($invitation, 'https://example.test/einladung/raw'))
        ->toMail($invitation);

    app(UpdateStudioSetting::class)('mail_from_address', 'studio@nordlicht.test', $owner);
    app(UpdateStudioSetting::class)('mail_from_name', 'Atelier Nordlicht', $owner);

    $second = (new StaffInvited($invitation, 'https://example.test/einladung/raw'))
        ->toMail($invitation);

    expect($second->from)->toBe(['studio@nordlicht.test', 'Atelier Nordlicht'])
        ->and($second->from)->not->toBe($first->from);
});

it(
    "carries the same legal particulars in StaffInvited's footer that the studio.* merge fields read",
    function () {
        $owner = staffMember(StaffRole::Admin);
        $invitation = t06Invitation();
        app(UpdateStudioSetting::class)('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
        app(UpdateStudioSetting::class)('city', 'Hamburg', $owner);

        $studio = StudioSettings::current()->profile();

        $message = (new StaffInvited($invitation, 'https://example.test/einladung/raw'))
            ->toMail($invitation);
        expect(renderedMail($message))->toContain($studio['legal_name'])
            ->toContain($studio['city']);
    }
);

it("renders the studio's legal particulars inside the layout's footer zone, not as a body paragraph", function () {
    $owner = staffMember(StaffRole::Admin);
    $contact = Contact::factory()->create();
    app(UpdateStudioSetting::class)('legal_name', 'Atelier Nordlicht Fotografie GmbH', $owner);
    app(UpdateStudioSetting::class)('city', 'Hamburg', $owner);

    $html = renderedMail((new ClientAccessLink($contact, 'https://example.test/zugang'))->toMail($contact));

    // The slot the views set and the slot the compiled layout reads were
    // named differently once, which dropped every footer silently. Assert on
    // the rendered document so a rename cannot do that again.
    expect($html)->toContain('Atelier Nordlicht Fotografie GmbH')
        ->toContain('Hamburg');
});

it('renders the product footer on a mail that carries its own brand', function (?string $address) {
    config(['mail.support_address' => $address]);

    $html = (string) app(Markdown::class)->render('mail.product-message', [
        'brand' => 'Auri CRM',
        'greeting' => 'Hallo Christian,',
        'introLines' => ['Testzeile.'],
        'outroLines' => [],
        'salutation' => null,
    ]);

    $address
        ? expect($html)->toContain('href="mailto:hilfe@auri.test"')
        : expect($html)->not->toContain('Fragen:')->not->toContain('mailto:');
})->with(['configured' => 'hilfe@auri.test', 'unset' => null]);
