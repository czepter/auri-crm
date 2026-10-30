<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Numbering\Models\NumberRange;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// staffMember() and props() live in tests/Pest.php — see the note there.
// No new global helper here — the seeded quote range is a one-line query
// used six times in this file, so a beforeEach binding it to a local is
// enough. A global function would risk colliding with a same-named one a
// sibling story's test file declares once both merge.
beforeEach(function () {
    $this->quoteRange = fn () => NumberRange::query()->where('document_type', DocumentType::Quote->value)->sole();
});

it('renders the numbering settings shell for an owner, one row per document type with a preview', function () {
    $props = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/nummernkreise')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['ranges'])->toHaveCount(3);

    $quote = collect($props['ranges'])->firstWhere('documentType', 'quote');
    expect($quote['id'])->toBe(($this->quoteRange)()->public_id)
        ->and($quote['prefix'])->toBe('AN')
        ->and($quote['resetRule'])->toBe('yearly')
        ->and($quote['startingCounter'])->toBe(1)
        ->and($quote['canSetStartingCounter'])->toBeTrue()
        ->and($quote['nextNumberPreview'])->toBe('AN-'.now('UTC')->year.'-1');
});

it('updates a range and leaves an audit row naming the owner and every changed value', function () {
    $owner = staffMember(StaffRole::Admin);
    $range = ($this->quoteRange)();

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/nummernkreise/{$range->public_id}", [
            'prefix' => 'ANG',
            'reset_rule' => 'continuous',
            'starting_counter' => 1,
        ])
        ->assertRedirect('/einstellungen/nummernkreise');

    $updated = $range->fresh();
    expect($updated->prefix)->toBe('ANG')
        ->and($updated->reset_rule)->toBe(NumberRangeResetRule::Continuous);

    $audit = AuditLog::where('event', 'number_range.updated')->sole();
    expect($audit->actor_id)->toBe($owner->id)
        ->and($audit->context['document_type'])->toBe('quote')
        ->and($audit->context['changes'])->toHaveKeys(['prefix', 'reset_rule']);
});

it('raises the starting counter before anything has been issued, and the next allocation reflects it', function () {
    $owner = staffMember(StaffRole::Admin);
    $range = ($this->quoteRange)();

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/nummernkreise/{$range->public_id}", [
            'prefix' => $range->prefix,
            'reset_rule' => $range->reset_rule->value,
            'starting_counter' => 1047,
        ])
        ->assertRedirect('/einstellungen/nummernkreise');

    $result = app(AllocateDocumentNumber::class)(config('tenancy.tenant_id'), now('UTC')->year, DocumentType::Quote);

    expect($result['counter'])->toBe(1047);
});

it('refuses the same starting counter change once a document has been issued for the period', function () {
    $owner = staffMember(StaffRole::Admin);
    $range = ($this->quoteRange)();

    app(AllocateDocumentNumber::class)(config('tenancy.tenant_id'), now('UTC')->year, DocumentType::Quote);

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/nummernkreise/{$range->public_id}", [
            'prefix' => $range->prefix,
            'reset_rule' => $range->reset_rule->value,
            'starting_counter' => 1047,
        ])
        ->assertSessionHasErrors('starting_counter');

    expect($range->fresh()->starting_counter)->toBe(1);
});

it('still allows a prefix or reset rule change after a document has been issued, informationally only', function () {
    $owner = staffMember(StaffRole::Admin);
    $range = ($this->quoteRange)();

    app(AllocateDocumentNumber::class)(config('tenancy.tenant_id'), now('UTC')->year, DocumentType::Quote);

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/nummernkreise/{$range->public_id}", [
            'prefix' => 'ANG',
            'reset_rule' => 'continuous',
            'starting_counter' => 1,
        ])
        ->assertRedirect('/einstellungen/nummernkreise')
        ->assertSessionDoesntHaveErrors();

    expect($range->fresh()->prefix)->toBe('ANG');
});

it('marks the starting counter as no longer settable in the settings props once a document has been issued', function () {
    app(AllocateDocumentNumber::class)(config('tenancy.tenant_id'), now('UTC')->year, DocumentType::Quote);

    $props = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/nummernkreise')
        ->assertOk()
        ->viewData('page')['props'];

    $quote = collect($props['ranges'])->firstWhere('documentType', 'quote');
    expect($quote['canSetStartingCounter'])->toBeFalse();
});

it('rejects a prefix that is not all uppercase letters', function () {
    $range = ($this->quoteRange)();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/nummernkreise/{$range->public_id}", [
            'prefix' => 'an-2026',
            'reset_rule' => 'yearly',
            'starting_counter' => 1,
        ])
        ->assertSessionHasErrors('prefix');
});

it('opens the numbering screen to the owner, and gives every other role the same response as a route that does not exist', function (StaffRole $role, int $status) {
    $missing = $this->actingAs(staffMember($role), 'staff')->get('/this-route-does-not-exist-xyz');

    $response = $this->actingAs(staffMember($role), 'staff')->get('/einstellungen/nummernkreise');

    $response->assertStatus($status);

    if ($status === 404) {
        expect($response->status())->toBe($missing->status());
    }
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
    [StaffRole::Contributor, 404],
]);

it('declares an audience on the numbering routes, same as every other admin route', function () {
    $routes = routesForSurface('admin');

    $numberingRoutes = collect($routes)->filter(fn ($route) => str_starts_with($route->uri(), 'einstellungen/nummernkreise'));

    expect($numberingRoutes)->toHaveCount(2)
        ->and($numberingRoutes->every(
            fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'audience:'))
        ))->toBeTrue();
});
