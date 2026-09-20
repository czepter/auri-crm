<?php

use App\Enums\StaffRole;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::store('redis')->flush());

// G-14: der eigene Screen ist weg, die Vorgaben stehen auf /galerien. Ein
// Lesezeichen darauf landet im richtigen Abschnitt.
it('sends the old gallery settings screen to the room', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/galerien')
        ->assertRedirect('/galerien?einstellungen=standardwerte');
});

it('updates the gallery defaults without touching the portal switches', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/portal', [
            'portal_maintenance_enabled' => true,
            'portal_accept_quotes_enabled' => true,
            'portal_payment_status_enabled' => false,
            'portal_messaging_mode' => 'any',
        ])->assertRedirect();

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/galerien', [
            'gallery_default_expiry_days' => 90,
            'gallery_default_downloads_enabled' => true,
            'gallery_default_lists_enabled' => false,
            'gallery_default_comments_enabled' => false,
            'gallery_default_design' => ['cover' => 'split', 'type' => 'serif', 'colour' => 'warm', 'grid' => 'dense'],
        ])->assertRedirect('/galerien');

    $row = StudioSettings::query()->sole();

    expect($row->gallery_default_expiry_days)->toBe(90)
        ->and($row->gallery_default_lists_enabled)->toBeFalse()
        ->and($row->gallery_default_design['colour'])->toBe('warm')
        ->and($row->portal_maintenance_enabled)->toBeTrue()
        ->and($row->portal_messaging_mode)->toBe('any');
});

it('no longer accepts a gallery default on the portal route', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/portal', ['gallery_default_expiry_days' => 5])
        ->assertRedirect();

    expect(StudioSettings::query()->sole()->gallery_default_expiry_days)->toBeNull();
});

// Das Tor sitzt jetzt am Schreiben: der Weg dorthin ist eine Weiterleitung
// auf eine Liste, die diese Rollen ohnehin sehen duerfen, und der Raum geht
// ohne Vorgaben in den Props gar nicht erst auf.
it('gives every non-owner role the same response on the gallery settings write as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->patch('/einstellungen/galerien', [
        'gallery_default_downloads_enabled' => true,
        'gallery_default_lists_enabled' => true,
        'gallery_default_comments_enabled' => true,
    ]);

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Owner, 302],
    [StaffRole::Photographer, 404],
    [StaffRole::Editor, 404],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('declares an audience on the gallery settings route, same as every other admin route', function () {
    $route = collect(routesForSurface('admin'))
        ->firstWhere(fn ($route) => $route->uri() === 'einstellungen/galerien');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
