<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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
            'working_days' => [1, 2, 3, 4, 5],
            'portal_legal_datenschutz_url' => 'not-a-url',
        ])
        ->assertInvalid(['portal_legal_datenschutz_url']);
});

it('stores the legal links and where a privacy request goes on the general route', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/allgemein', [
            'working_days' => [1, 2, 3, 4, 5],
            'portal_legal_datenschutz_url' => 'https://atelier-nordlicht.de/datenschutz',
            'portal_legal_agb_url' => 'https://atelier-nordlicht.de/agb',
            'dsgvo_request_recipient' => 'datenschutz@atelier-nordlicht.de',
            'dsgvo_request_deadline_days' => 14,
        ])
        ->assertRedirect();

    $row = StudioSettings::query()->sole();

    expect($row->portal_legal_datenschutz_url)->toBe('https://atelier-nordlicht.de/datenschutz')
        ->and($row->portal_legal_agb_url)->toBe('https://atelier-nordlicht.de/agb')
        ->and($row->dsgvo_request_recipient)->toBe('datenschutz@atelier-nordlicht.de')
        ->and($row->dsgvo_request_deadline_days)->toBe(14);
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
            'working_days' => [1, 2, 3, 4, 5],
            'dsgvo_request_recipient' => 'datenschutz@atelier-nordlicht.de',
        ])
        ->assertRedirect();

    $row = StudioSettings::query()->sole();

    expect($row->dsgvo_request_recipient)->toBe('datenschutz@atelier-nordlicht.de')
        ->and($row->portal_maintenance_enabled)->toBeTrue()
        ->and($row->portal_messaging_mode)->toBe('any')
        ->and($row->portal_accept_quotes_enabled)->toBeTrue();
});
