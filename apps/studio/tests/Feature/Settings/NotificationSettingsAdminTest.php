<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember(), props() and routesForSurface() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands quiet hours to the settings screen, off by default', function () {
    test()->actingAs(staffMember(StaffRole::Owner), 'staff');

    $props = props('/einstellungen/benachrichtigungen');

    expect($props)
        ->toHaveKey('quietHoursEnabled', false)
        ->toHaveKey('quietHoursStart', null)
        ->toHaveKey('quietHoursEnd', null);
});

it('turns quiet hours on, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    test()->actingAs($owner, 'staff')
        ->patch('/einstellungen/benachrichtigungen', [
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ])
        ->assertRedirect('/einstellungen/benachrichtigungen');

    $row = StudioSettings::query()->sole();

    expect($row->quiet_hours_enabled)->toBeTrue()
        ->and($row->quiet_hours_start)->toBe('22:00:00')
        ->and($row->quiet_hours_end)->toBe('07:00:00')
        ->and(AuditLog::where('event', 'studio_settings.updated')
            ->where('context->key', 'quiet_hours_enabled')->sole()->actor_id)->toBe($owner->id);
});

it('turns quiet hours back off and clears both times', function () {
    $owner = staffMember(StaffRole::Owner);

    test()->actingAs($owner, 'staff')->patch('/einstellungen/benachrichtigungen', [
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
    ]);

    test()->actingAs($owner, 'staff')->patch('/einstellungen/benachrichtigungen', [
        'quiet_hours_enabled' => false,
    ]);

    $row = StudioSettings::query()->sole();

    expect($row->quiet_hours_enabled)->toBeFalse()
        ->and($row->quiet_hours_start)->toBeNull()
        ->and($row->quiet_hours_end)->toBeNull();
});

it('requires both times when quiet hours is enabled', function () {
    test()->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/benachrichtigungen', ['quiet_hours_enabled' => true])
        ->assertInvalid(['quiet_hours_start', 'quiet_hours_end']);
});

it('rejects a time outside H:i', function () {
    test()->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/benachrichtigungen', [
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => 'not-a-time',
            'quiet_hours_end' => '07:00',
        ])
        ->assertInvalid(['quiet_hours_start']);
});

it('gives every non-owner role the same response on the notifications route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = test()->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = test()->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen/benachrichtigungen', ['quiet_hours_enabled' => false]);

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

it('declares an audience on the notifications settings route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/benachrichtigungen');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
