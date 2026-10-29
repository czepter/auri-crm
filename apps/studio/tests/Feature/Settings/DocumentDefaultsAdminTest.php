<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

beforeEach(fn () => Cache::store('redis')->flush());

it('hands the payment and receipt defaults to their pages', function (string $path, string $pane) {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pane', $pane)->has('settings', fn ($settings) => $settings
            ->has('documentLayout')
            ->where('quoteValidityDays', 14)
            ->where('paymentTermDays', 14)
            ->where('depositPercentage', 3000)
            ->where('remindersEnabled', true)
            ->etc()
        ));
})->with([
    ['/einstellungen/zahlung', 'payment'],
    ['/einstellungen/belege', 'receipts'],
]);

it('updates the payment defaults, redirects, and audits the owner', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')
        ->from('/einstellungen/zahlung')
        ->patch('/einstellungen/zahlung', [
            'payment_term_days' => 21,
            'deposit_percentage' => 5000,
            'payments_reminders_enabled' => false,
        ])
        ->assertRedirect('/einstellungen/zahlung');

    $row = StudioSettings::query()->sole();

    expect($row->payment_term_days)->toBe(21)
        ->and($row->deposit_percentage)->toBe(5000)
        ->and($row->payments_reminders_enabled)->toBeFalse()
        ->and(AuditLog::where('event', 'studio_settings.updated')->where('context->key', 'payment_term_days')->sole()->actor_id)->toBe($owner->id);
});

it('updates the receipt settings and leaves the payment fields alone', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->from('/einstellungen/belege')
        ->patch('/einstellungen/belege', [
            'quote_validity_days' => 30,
            'document_layout' => 'minimal',
            'document_letterhead_html' => '<header>Nordlicht</header>',
            'document_footer_html' => '<footer>Seite 1</footer>',
        ])
        ->assertRedirect('/einstellungen/belege');

    $row = StudioSettings::query()->sole();

    expect($row->quote_validity_days)->toBe(30)
        ->and($row->document_layout)->toBe('minimal')
        ->and($row->document_letterhead_html)->toBe('<header>Nordlicht</header>')
        ->and($row->document_footer_html)->toBe('<footer>Seite 1</footer>')
        ->and($row->payment_term_days)->toBe(14)
        ->and($row->deposit_percentage)->toBe(3000);
});

it('saves only the fields a page sends', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/belege', ['quote_validity_days' => 30]);
    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlung', ['payment_term_days' => 7]);

    $row = StudioSettings::query()->sole();

    expect($row->quote_validity_days)->toBe(30)
        ->and($row->payment_term_days)->toBe(7)
        ->and($row->document_layout)->toBe('classic')
        ->and($row->deposit_percentage)->toBe(3000);
});

it('rejects a deposit_percentage outside 0-10000 basis points', function () {
    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch('/einstellungen/zahlung', ['deposit_percentage' => 10001])
        ->assertInvalid(['deposit_percentage']);
});

it('rejects a negative payment_term_days or quote_validity_days', function () {
    $owner = staffMember(StaffRole::Admin);

    $this->actingAs($owner, 'staff')->patch('/einstellungen/zahlung', ['payment_term_days' => -1])->assertInvalid(['payment_term_days']);
    $this->actingAs($owner, 'staff')->patch('/einstellungen/belege', ['quote_validity_days' => -1])->assertInvalid(['quote_validity_days']);
});

it('gives every non-owner role the same response on the payment route as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')
        ->patch('/einstellungen/zahlung', ['payment_term_days' => 14]);

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Admin, 302],
    [StaffRole::Manager, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
]);

it('declares an audience on the payment and receipt routes, same as every other admin route', function (string $uri) {
    $route = collect(routesForSurface('admin'))->firstWhere(fn ($route) => $route->uri() === $uri && in_array('PATCH', $route->methods(), true));

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:')))->toBeTrue();
})->with(['einstellungen/zahlung', 'einstellungen/belege']);
