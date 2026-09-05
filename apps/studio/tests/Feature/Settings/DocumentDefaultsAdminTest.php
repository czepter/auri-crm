<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Settings\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the document defaults to the settings screen', function () {
    $props = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->get('/einstellungen')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['settings'])
        ->toHaveKey('quoteValidityDays', 14)
        ->toHaveKey('paymentTermDays', 14)
        ->toHaveKey('depositPercentage', 3000)
        ->toHaveKey('widerrufIncluded', true)
        ->toHaveKey('widerrufText');
});

it('updates the document defaults, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Owner);

    $this->actingAs($owner, 'staff')
        ->patch('/einstellungen/dokumente', [
            'quote_validity_days' => 30,
            'payment_term_days' => 21,
            'deposit_percentage' => 5000,
            'widerruf_included' => false,
            'widerruf_text' => 'Angepasster Widerrufstext.',
            'document_letterhead_html' => '<header>Nordlicht</header>',
            'document_footer_html' => '<footer>Seite 1</footer>',
        ])
        ->assertRedirect('/einstellungen');

    $row = StudioSettings::query()->sole();

    expect($row->quote_validity_days)->toBe(30)
        ->and($row->payment_term_days)->toBe(21)
        ->and($row->deposit_percentage)->toBe(5000)
        ->and($row->widerruf_included)->toBeFalse()
        ->and($row->widerruf_text)->toBe('Angepasster Widerrufstext.')
        ->and($row->document_letterhead_html)->toBe('<header>Nordlicht</header>')
        ->and($row->document_footer_html)->toBe('<footer>Seite 1</footer>')
        ->and(AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'quote_validity_days')->sole()->actor_id)->toBe($owner->id);
});

it('rejects a deposit_percentage outside 0-10000 basis points', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/dokumente', [
            'quote_validity_days' => 14,
            'payment_term_days' => 14,
            'deposit_percentage' => 10001,
            'widerruf_included' => true,
        ])
        ->assertInvalid(['deposit_percentage']);
});

it('rejects a negative quote_validity_days or payment_term_days', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->patch('/einstellungen/dokumente', [
            'quote_validity_days' => -1,
            'payment_term_days' => 14,
            'deposit_percentage' => 3000,
            'widerruf_included' => true,
        ])
        ->assertInvalid(['quote_validity_days']);
});

it('gives every non-owner role the same response on the document defaults route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen/dokumente', [
            'quote_validity_days' => 14,
            'payment_term_days' => 14,
            'deposit_percentage' => 3000,
            'widerruf_included' => true,
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

it('declares an audience on the document defaults route, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $route = collect($routes)->firstWhere(fn ($route) => $route->uri() === 'einstellungen/dokumente');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
});
