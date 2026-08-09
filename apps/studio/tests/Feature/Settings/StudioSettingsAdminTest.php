<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('renders the settings shell for an owner', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings']['id'])->toBe(StudioSettings::query()->sole()->public_id)
        ->and($props['settings'])->toHaveKey('displayName');
});

it('updates the display name, redirects, and leaves an audit row naming the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen', ['display_name' => 'Atelier Nordlicht'])
        ->assertRedirect('/einstellungen');

    expect(StudioSettings::query()->sole()->display_name)->toBe('Atelier Nordlicht')
        ->and(AuditLog::where('event', 'studio_settings.updated')->sole()->actor_id)->toBe($owner->id);
});

it('opens the settings screen to the owner, and gives every other role the same response as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->get('/einstellungen');

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

it('declares an audience on the settings routes, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $settingsRoutes = collect($routes)->filter(fn ($route) => $route->uri() === 'einstellungen');

    expect($settingsRoutes)->toHaveCount(2)
        ->and($settingsRoutes->every(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:'))
        ))->toBeTrue();
});

it('lists Einstellungen in the sidebar only for a role that manages settings', function () {
    $owner = $this->actingAs(staffMember(StaffRole::Owner), 'staff')->get('/')->assertOk();
    expect($owner->viewData('page')['props']['auth']['can'])->toContain('manage_settings');

    $photographer = $this->actingAs(staffMember(StaffRole::Photographer), 'staff')->get('/')->assertOk();
    expect($photographer->viewData('page')['props']['auth']['can'])->not->toContain('manage_settings');
});
