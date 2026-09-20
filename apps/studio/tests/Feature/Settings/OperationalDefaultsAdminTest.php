<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember(), props() and routesForSurface() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the timezone to the settings screen', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/allgemein')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings'])->toHaveKey('timezone', config('app.timezone'));
});

it('updates timezone and the register, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/allgemein')
        ->patch('/einstellungen', [
            'timezone' => 'Europe/Vienna',
            'client_register' => 'informal',
        ])
        ->assertRedirect('/einstellungen/allgemein');

    $row = StudioSettings::query()->sole();

    expect($row->timezone)->toBe('Europe/Vienna')
        ->and($row->client_register)->toBe('informal')
        ->and(AuditLog::where('event', 'studio_settings.updated')
            ->where('context->key', 'timezone')->sole()->actor_id)->toBe($owner->id);
});

it('clears the tenant timezone back to the environment fallback when submitted blank', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')->patch('/einstellungen', ['timezone' => 'Europe/Vienna']);

    $this->actingAs($owner, 'staff')->patch('/einstellungen', ['timezone' => null]);

    $row = StudioSettings::query()->sole();

    expect($row->timezone)->toBeNull();
});

it('rejects a timezone that is not a real IANA identifier', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen', ['timezone' => 'Nonsense/Place'])
        ->assertInvalid(['timezone']);
});

it('gives every non-owner role the same response on the general settings route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen', ['timezone' => 'Europe/Vienna']);

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
