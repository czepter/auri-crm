<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember(), props() and routesForSurface() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the timezone, locale and working days to the settings screen', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings'])
        ->toHaveKey('timezone', config('app.timezone'))
        ->toHaveKey('locale', config('app.locale'))
        ->toHaveKey('workingDays', [1, 2, 3, 4, 5, 6, 7]);
});

it('updates timezone, locale and working days, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/allgemein', [
            'timezone' => 'Europe/Vienna',
            'locale' => 'de_AT',
            'working_days' => [1, 2, 3, 4, 5],
        ])
        ->assertRedirect('/einstellungen');

    $row = StudioSettings::query()->sole();

    expect($row->timezone)->toBe('Europe/Vienna')
        ->and($row->locale)->toBe('de_AT')
        ->and($row->working_days)->toBe([1, 2, 3, 4, 5])
        ->and(AuditLog::where('event', 'studio_settings.updated')
            ->where('context->key', 'timezone')->sole()->actor_id)->toBe($owner->id);
});

it('clears the tenant timezone and locale back to the environment fallback when submitted blank', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/allgemein', [
        'timezone' => 'Europe/Vienna',
        'locale' => 'de_AT',
        'working_days' => [1, 2, 3, 4, 5],
    ]);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/allgemein', [
        'timezone' => null,
        'locale' => null,
        'working_days' => [1, 2, 3, 4, 5],
    ]);

    $row = StudioSettings::query()->sole();

    expect($row->timezone)->toBeNull()
        ->and($row->locale)->toBeNull();
});

it('rejects a timezone that is not a real IANA identifier', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/allgemein', ['timezone' => 'Nonsense/Place', 'working_days' => [1, 2, 3, 4, 5]])
        ->assertInvalid(['timezone']);
});

it('rejects a locale outside the three DACH variants', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/allgemein', ['locale' => 'en_US', 'working_days' => [1, 2, 3, 4, 5]])
        ->assertInvalid(['locale']);
});

it('rejects an empty working-days selection', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/allgemein', ['working_days' => []])
        ->assertInvalid(['working_days']);
});

it('rejects a working day outside 1-7', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/allgemein', ['working_days' => [0, 1, 2]])
        ->assertInvalid(['working_days.0']);
});

it('gives every non-owner role the same response on the general settings route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen/allgemein', ['working_days' => [1, 2, 3, 4, 5]]);

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

it('declares an audience on the general settings route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/allgemein');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
