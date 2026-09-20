<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// staffMember(), routesForSurface() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands every portal setting to the settings screen, every capability switch off by default', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/portal')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings'])
        ->toHaveKey('maintenanceEnabled', false)
        ->toHaveKey('acceptQuotesEnabled', false)
        ->toHaveKey('paymentStatusEnabled', false)
        ->toHaveKey('messagingMode', 'off');
});

it('updates the maintenance switch, its message and the welcome text, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal', [
            'portal_maintenance_enabled' => true,
            'portal_maintenance_message' => 'Das Portal ist gerade nicht erreichbar.',
            'portal_welcome_active' => 'Schön, dass Sie da sind.',
            'portal_accept_quotes_enabled' => false,
            'portal_payment_status_enabled' => true,
            'portal_messaging_mode' => 'booking',
        ])
        ->assertRedirect();

    $row = StudioSettings::query()->sole();

    expect($row->portal_maintenance_enabled)->toBeTrue()
        ->and($row->portal_maintenance_message)->toBe('Das Portal ist gerade nicht erreichbar.')
        ->and($row->portal_welcome_active)->toBe('Schön, dass Sie da sind.')
        ->and($row->portal_payment_status_enabled)->toBeTrue()
        ->and($row->portal_accept_quotes_enabled)->toBeFalse()
        ->and($row->portal_messaging_mode)->toBe('booking')
        ->and(AuditLog::where('event', 'studio_settings.updated')
            ->where('context->key', 'portal_maintenance_enabled')->sole()->actor_id)->toBe($owner->id);
});

// Die Vorschau neben dem Formular ist die Portal-Startseite selbst. Sie
// zeigt den Text des Zustands, den der Umschalter nennt.
it('renders the portal home preview with the greeting of the asked state', function () {
    StudioSettings::query()->update([
        'portal_welcome_inquiry' => 'Wir haben eure Anfrage.',
        'portal_welcome_done' => 'Eure Bilder liegen weiter hier.',
    ]);

    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/portal/vorschau?zustand=done')
        ->viewData('page')['props'];

    expect($props['welcomeText'])->toBe('Eure Bilder liegen weiter hier.')
        ->and($props['portalBranding'])->toHaveKey('displayName')
        ->and($props['portalLegal'])->toHaveKey('impressumUrl');
});

// Getippt schlägt gespeichert, sonst stünde die Vorschau still, bis jemand
// speichert.
it('prefers the typed greeting over the saved one', function () {
    StudioSettings::query()->update(['portal_welcome_inquiry' => 'Wir haben eure Anfrage.']);

    expect($this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/portal/vorschau?zustand=inquiry&text=Noch+nicht+gespeichert')
        ->viewData('page')['props']['welcomeText'])->toBe('Noch nicht gespeichert');
});

// Die Rechtstexte stehen unter Allgemein: sie sagen etwas über das Studio,
// nicht über das Portal, in dem sie nur auftauchen.
it('rejects a Datenschutz link that is not a URL', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/allgemein', [
            'portal_legal_datenschutz_url' => 'not-a-url',
        ])
        ->assertInvalid(['portal_legal_datenschutz_url']);
});

it('stores the legal links on the general route', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/allgemein', [
            'portal_legal_datenschutz_url' => 'https://atelier-nordlicht.de/datenschutz',
            'portal_legal_agb_url' => 'https://atelier-nordlicht.de/agb',
        ])
        ->assertRedirect();

    $row = StudioSettings::query()->sole();

    expect($row->portal_legal_datenschutz_url)->toBe('https://atelier-nordlicht.de/datenschutz')
        ->and($row->portal_legal_agb_url)->toBe('https://atelier-nordlicht.de/agb');
});

it('gives every non-owner role the same response on the portal settings route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->get('/einstellungen/portal');

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 404],
    [StaffRole::Editor, 404],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('declares an audience on the portal settings route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/portal');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});

it('leaves the settings a pane does not show untouched', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal', [
            'portal_maintenance_enabled' => true,
            'portal_accept_quotes_enabled' => true,
            'portal_payment_status_enabled' => false,
            'portal_messaging_mode' => 'any',
        ])
        ->assertRedirect();

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/allgemein', [
            'portal_legal_datenschutz_url' => 'https://atelier-nordlicht.de/datenschutz',
        ])
        ->assertRedirect();

    $row = StudioSettings::query()->sole();

    expect($row->portal_legal_datenschutz_url)->toBe('https://atelier-nordlicht.de/datenschutz')
        ->and($row->portal_maintenance_enabled)->toBeTrue()
        ->and($row->portal_messaging_mode)->toBe('any')
        ->and($row->portal_accept_quotes_enabled)->toBeTrue();
});

// Der Kontakt ist eine Person und steht deshalb auf einem eigenen Bildschirm:
// Name und Bild waren vorher nirgends zu hinterlegen.
it('stores the portal contact person, its picture and serves the picture on both surfaces', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal/kontakt', [
            'portal_contact_name' => 'Jana Wolf',
            'portal_contact_phone' => '+49 40 4321987',
            'portal_contact_email' => 'jana@atelier-nordlicht.de',
            'portal_contact_response_time' => 'Antwort binnen 24 Stunden',
            'portal_contact_avatar' => UploadedFile::fake()->image('kontakt.png'),
        ])
        ->assertSessionHasNoErrors();

    $row = StudioSettings::query()->sole();

    expect($row->portal_contact_name)->toBe('Jana Wolf')
        ->and($row->portal_contact_response_time)->toBe('Antwort binnen 24 Stunden')
        ->and($row->portal_contact_avatar_path)->not->toBeNull();

    Storage::disk('local')->assertExists($row->portal_contact_avatar_path);

    expect(AuditLog::where('event', 'studio_settings.updated')
        ->where('context->key', 'portal_contact_avatar_path')->sole()->actor_id)->toBe($owner->id);

    $props = $this->get('/einstellungen/portal/kontakt')->assertOk()->viewData('page')['props'];

    expect($props['settings']['contactName'])->toBe('Jana Wolf');

    $this->get($props['settings']['contactAvatarUrl'])->assertOk();
});

it('removes the contact picture and the file behind it', function () {
    Storage::fake('local');
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal/kontakt', [
            'portal_contact_avatar' => UploadedFile::fake()->image('kontakt.png'),
        ])
        ->assertSessionHasNoErrors();

    $path = StudioSettings::query()->sole()->portal_contact_avatar_path;

    $this->actingAs($owner, 'staff')
        ->delete('/einstellungen/portal/kontakt/bild')
        ->assertRedirect();

    expect(StudioSettings::query()->sole()->portal_contact_avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

// Aus einer Kamera kommen acht Megapixel für einen Kreis von 54. Was ankommt,
// ist nicht, was liegen bleibt.
it('shrinks the uploaded contact picture to the width the card needs', function () {
    Storage::fake('local');

    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/portal/kontakt', [
            'portal_contact_avatar' => UploadedFile::fake()->image('kontakt.jpg', 2400, 1600),
        ])
        ->assertSessionHasNoErrors();

    $path = StudioSettings::query()->sole()->portal_contact_avatar_path;
    $stored = imagecreatefromstring(Storage::disk('local')->get($path));

    expect($path)->toEndWith('.webp')
        ->and(imagesx($stored))->toBe(320)
        ->and(imagesy($stored))->toBe(213);
});

// Der Browser zeigte seine eigene Sprechblase und das Formular kam nie beim
// Server an; jetzt steht der Fehler unter dem Feld.
it('rejects a contact address that is not one, and a number that is not digits', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal/kontakt', [
            'portal_contact_email' => 'hi@czepter,de',
            'portal_contact_phone' => '017678697329as dasd',
        ])
        ->assertSessionHasErrors(['portal_contact_email', 'portal_contact_phone']);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal/kontakt', [
            'portal_contact_email' => 'hi@czepter.de',
            'portal_contact_phone' => '+49 176 78697329',
        ])
        ->assertSessionHasNoErrors();
});
