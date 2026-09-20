<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the VAT mode, rate and note to the settings screen', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen/allgemein')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings'])
        ->toHaveKey('vatMode', 'standard')
        ->toHaveKey('vatRate', 1900)
        ->toHaveKey('vatNote', 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');
});

it('updates vat_mode, vat_rate and vat_note, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/rechnungsdaten')
        ->patch('/einstellungen/ust', [
            'vat_mode' => 'small_business',
            'vat_rate' => 1900,
            'vat_note' => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
        ])
        ->assertRedirect('/einstellungen/rechnungsdaten');

    $row = StudioSettings::query()->sole();

    expect($row->vat_mode)->toBe('small_business')
        ->and(AuditLog::where('event', 'studio_settings.updated')
            ->where('context->key', 'vat_mode')->sole()->actor_id)->toBe($owner->id);
});

it('rejects a vat_mode outside standard and small_business', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/ust', ['vat_mode' => 'nonsense', 'vat_rate' => 1900])
        ->assertInvalid(['vat_mode']);
});

it('rejects a vat_rate outside 0-10000 basis points', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/ust', ['vat_mode' => 'standard', 'vat_rate' => 10001])
        ->assertInvalid(['vat_rate']);
});

it('gives every non-owner role the same response on the VAT route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen/ust', ['vat_mode' => 'standard', 'vat_rate' => 1900]);

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

it('declares an audience on the VAT settings route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $vatRoute = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/ust');

    expect($vatRoute)->not->toBeNull()
        ->and(collect($vatRoute->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
