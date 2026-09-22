<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Portal\Actions\ConsumeClientAccess;
use App\Portal\Models\ClientAccess;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A preview link, as ContactController::previewPortal() mints one. */
function previewLink(Contact $contact, ?User $staff = null): string
{
    $token = bin2hex(random_bytes(32));

    ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(ClientAccess::VALID_FOR_MINUTES),
        'preview_by_id' => ($staff ?? User::factory()->create())->id,
    ]);

    return "/zugang/{$token}";
}

it('opens the client\'s own portal, marked as a preview', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();

    // Two, so the preview lands on the home instead of being sent into the
    // one booking.
    foreach ([Project::factory()->create(['portal_visible' => true]), Project::factory()->create(['portal_visible' => true])] as $project) {
        $project->attachContact($contact, ProjectContactRole::Primary);
    }

    test()->get(previewLink($contact))->assertRedirect('/');

    $props = test()->get('/')->assertOk()->viewData('page')['props'];

    expect($props['contact']['name'])->toBe($contact->name)
        ->and($props['portalPreview'])->toBeTrue();
});

// The whole reason a preview is allowed to exist at all: a studio owner
// looking at what a client sees must not be able to act as them.
it('refuses every write made from a preview session', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create(['name' => 'Lena Meier']);

    test()->get(previewLink($contact))->assertRedirect('/');

    test()->patch('/einstellungen', ['name' => 'Umbenannt', 'email' => $contact->email])
        ->assertForbidden();

    expect($contact->fresh()->name)->toBe('Lena Meier');
});

it('still lets a real client session write', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();

    test()->actingAs($contact, 'portal')
        ->patch('/einstellungen', ['name' => 'Umbenannt', 'email' => $contact->email])
        ->assertRedirect();

    expect($contact->fresh()->name)->toBe('Umbenannt');
});

// Inherited from ClientAccess unchanged — the point of not building a
// second single-use-token mechanism beside it.
it('spends a preview link exactly once', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $link = previewLink($contact);
    $token = substr($link, strlen('/zugang/'));

    test()->get($link)->assertRedirect('/');

    expect(ClientAccess::sole()->consumed_at)->not->toBeNull();

    expect(app(ConsumeClientAccess::class)($token))->toBeNull();
});

// The link is the whole credential, so it outranks whatever session the
// browser is holding. Behind `guest:portal` it did not: a studio member
// previewing a second client kept landing on the first, with the second link
// left unspent.
it('signs the browser in as the link\'s contact, whoever it was holding', function () {
    useSurface(Surface::PORTAL);

    $holding = Contact::factory()->create();
    $wanted = Contact::factory()->create();

    test()->actingAs($holding, 'portal')->get(previewLink($wanted))->assertRedirect('/');

    expect(ClientAccess::sole()->consumed_at)->not->toBeNull()
        ->and(auth('portal')->id())->toBe($wanted->id);
});

it('refuses a preview link once it has expired', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $link = previewLink($contact);

    test()->travel(ClientAccess::VALID_FOR_MINUTES + 1)->minutes();

    test()->get($link)->assertRedirect('/anmelden');
});

it('refuses a preview link for a contact whose access the studio revoked', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $link = previewLink($contact);
    $contact->revokePortalAccess();

    test()->get($link)->assertRedirect('/anmelden');
});

it('audits the entry as a preview, naming the staff member behind it', function () {
    useSurface(Surface::PORTAL);

    $staff = User::factory()->create();
    $contact = Contact::factory()->create();

    test()->get(previewLink($contact, $staff))->assertRedirect('/');

    $row = AuditLog::where('event', 'portal.preview_entered')->sole();

    expect($row->context['staff'])->toBe($staff->public_id)
        ->and($row->context['contact'])->toBe($contact->public_id);
});

it('leaves an ordinary magic link audited as an ordinary sign-in', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create();
    $token = bin2hex(random_bytes(32));
    ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash($token),
        'expires_at' => now()->addMinutes(ClientAccess::VALID_FOR_MINUTES),
    ]);

    test()->get("/zugang/{$token}")->assertRedirect('/');

    expect(AuditLog::where('event', 'portal.link_consumed')->count())->toBe(1)
        ->and(test()->get('/')->viewData('page')['props']['portalPreview'])->toBeFalse();
});

it('ends a preview without touching the client\'s own sessions', function () {
    useSurface(Surface::PORTAL);

    $contact = Contact::factory()->create(['sessions_valid_from' => null]);

    test()->get(previewLink($contact));
    test()->get('/vorschau-beenden')->assertRedirect('/anmelden');

    expect($contact->fresh()->sessions_valid_from)->toBeNull();
});

it('lets a preview session sign out', function () {
    useSurface(Surface::PORTAL);

    test()->get(previewLink(Contact::factory()->create()));
    test()->post('/abmelden')->assertRedirect('/anmelden');

    expect(auth('portal')->check())->toBeFalse();
});

// Only somebody who could change these settings anyway may mint one.
it('mints a preview link for an owner and refuses everyone else', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create();

    // As the button sends it: a form POST into a second tab, so the answer
    // is a redirect that tab follows onto the portal's own origin.
    $response = test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-vorschau")
        ->assertStatus(302);

    expect($response->headers->get('Location'))
        ->toStartWith(config('portal.url').'/zugang/');

    expect(ClientAccess::query()->whereNotNull('preview_by_id')->count())->toBe(1)
        ->and(AuditLog::where('event', 'portal.preview_issued')->count())->toBe(1);

    test()->actingAs(staffMember(StaffRole::Contributor), 'staff')
        ->post("/kontakte/{$contact->public_id}/portal-vorschau")
        ->assertForbidden();

    expect(ClientAccess::query()->whereNotNull('preview_by_id')->count())->toBe(1);
});

it('gives a bare portal host a scheme', function () {
    $_ENV['PORTAL_URL'] = $_SERVER['PORTAL_URL'] = 'meine.example.de';

    try {
        expect((require config_path('portal.php'))['url'])->toBe('https://meine.example.de');
    } finally {
        unset($_ENV['PORTAL_URL'], $_SERVER['PORTAL_URL']);
    }
});

it('never renders a live preview credential into the page', function () {
    useSurface(Surface::ADMIN);

    $contact = Contact::factory()->create();

    $body = test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/kontakte/{$contact->public_id}")
        ->assertOk()
        ->getContent();

    expect($body)->not->toContain('/zugang/')
        ->and(ClientAccess::count())->toBe(0);
});
