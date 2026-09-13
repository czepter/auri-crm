<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Crm\Models\Lead;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists active and inactive packages with their item count', function () {
    $active = Package::factory()->has(PackageItem::factory()->count(2), 'items')->create(['name' => 'Hochzeit Basis']);
    Package::factory()->inactive()->create(['name' => 'Alt']);

    $props = $this->actingAs(staffMember(), 'staff')->get('/pakete')->assertOk()->viewData('page')['props'];

    expect(collect($props['packages'])->pluck('name'))->toContain('Hochzeit Basis', 'Alt')
        ->and(collect($props['packages'])->firstWhere('id', $active->public_id)['itemCount'])->toBe(2);
});

it('creates a package with items and logs it', function () {
    $user = staffMember();

    $this->actingAs($user, 'staff')->post('/pakete', [
        'name' => 'Hochzeit Basis',
        'description' => 'Ganztägige Begleitung',
        'items' => [
            ['title' => 'Fotograf ganztägig', 'description' => null, 'quantity' => 1, 'unit_price' => 129900],
            ['title' => 'Enthaltene Leistung', 'description' => null, 'quantity' => 1, 'unit_price' => null],
        ],
    ])->assertRedirect('/pakete');

    $package = Package::sole();

    expect($package->name)->toBe('Hochzeit Basis')
        ->and($package->is_active)->toBeTrue()
        ->and($package->items)->toHaveCount(2)
        ->and($package->items->last()->unit_price)->toBeNull()
        ->and($package->items->first()->unit_price)->toBe(129900)
        ->and($package->items->pluck('sort_order')->all())->toBe([0, 1]);

    $row = AuditLog::where('event', 'package.created')->sole();

    expect($row->actor_id)->toBe($user->id)
        ->and($row->subject)->toBe($package->public_id);
});

it('requires a name to create a package', function () {
    $this->actingAs(staffMember(), 'staff')
        ->post('/pakete', ['name' => '', 'items' => []])
        ->assertInvalid(['name']);

    expect(Package::count())->toBe(0);
});

it('shows a package with its items on the edit screen', function () {
    $package = Package::factory()->create(['name' => 'Hochzeit Basis']);
    PackageItem::factory()->for($package)->create(['title' => 'Fotograf', 'sort_order' => 0]);

    $props = $this->actingAs(staffMember(), 'staff')
        ->get("/pakete/{$package->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['package']['id'])->toBe($package->public_id)
        ->and($props['package']['items'])->toHaveCount(1)
        ->and($props['package']['items'][0]['title'])->toBe('Fotograf');
});

it('replaces a package\'s items wholesale on update, and logs it', function () {
    $user = staffMember();
    $package = Package::factory()->create(['name' => 'Alt']);
    $original = PackageItem::factory()->for($package)->create(['title' => 'Alte Leistung']);

    $this->actingAs($user, 'staff')->patch("/pakete/{$package->public_id}", [
        'name' => 'Neu benannt',
        'description' => null,
        'items' => [
            ['title' => 'Neue Leistung', 'description' => null, 'quantity' => 2, 'unit_price' => 5000],
        ],
    ])->assertRedirect();

    $package->refresh();

    expect($package->name)->toBe('Neu benannt')
        ->and($package->items)->toHaveCount(1)
        ->and($package->items->first()->title)->toBe('Neue Leistung')
        ->and(PackageItem::find($original->id))->toBeNull();

    expect(AuditLog::where('event', 'package.updated')->where('subject', $package->public_id)->exists())->toBeTrue();
});

it('toggles a package inactive and back, and logs each change', function () {
    $user = staffMember();
    $package = Package::factory()->create();

    $this->actingAs($user, 'staff')
        ->post("/pakete/{$package->public_id}/status")
        ->assertRedirect();

    expect($package->fresh()->is_active)->toBeFalse();
    expect(AuditLog::where('event', 'package.deactivated')->where('subject', $package->public_id)->exists())->toBeTrue();

    $this->post("/pakete/{$package->public_id}/status")->assertRedirect();

    expect($package->fresh()->is_active)->toBeTrue();
    expect(AuditLog::where('event', 'package.activated')->where('subject', $package->public_id)->exists())->toBeTrue();
});

it('excludes a deactivated package from the scope a future quote seeds from', function () {
    $available = Package::factory()->create();
    $retired = Package::factory()->inactive()->create();

    $ids = Package::active()->pluck('id');

    expect($ids)->toContain($available->id)->not->toContain($retired->id);
});

it('gives copyItems a deep, disconnected copy that a later edit cannot touch', function () {
    $package = Package::factory()->create();
    PackageItem::factory()->for($package)->create([
        'title' => 'Fotograf ganztägig',
        'description' => 'Inklusive Anfahrt',
        'quantity' => 1,
        'unit_price' => 129900,
        'sort_order' => 0,
    ]);
    PackageItem::factory()->for($package)->create([
        'title' => 'Album',
        'quantity' => 1,
        'unit_price' => null,
        'sort_order' => 1,
    ]);

    // Standing in for Q-02's seed step: a quote would copy this array in and
    // never touch the package again.
    $seed = $package->copyItems();

    $package->update(['name' => 'Renamed']);
    $package->items()->first()->update(['title' => 'Retitled', 'unit_price' => 1]);

    expect($seed)->toBe([
        [
            'title' => 'Fotograf ganztägig',
            'description' => 'Inklusive Anfahrt',
            'quantity' => 1,
            'unit_price' => 129900,
            // Q-09/Q-10 ride along, and just as deadly copied.
            'selectable' => false,
            'selected' => false,
            'outcome' => null,
            'image_path' => null,
        ],
        [
            'title' => 'Album',
            'description' => null,
            'quantity' => 1,
            'unit_price' => null,
            'selectable' => false,
            'selected' => false,
            'outcome' => null,
            'image_path' => null,
        ],
    ]);
});

it('leaves copyItems empty for a package with no items', function () {
    $package = Package::factory()->create();

    expect($package->copyItems())->toBe([]);
});

it('duplicates a package with independent items and a marked name', function () {
    $original = Package::factory()
        ->has(PackageItem::factory()->count(2), 'items')
        ->create(['name' => 'Hochzeit Basis', 'description' => 'Ganztags']);

    $this->actingAs(staffMember(), 'staff')
        ->post("/pakete/{$original->public_id}/duplizieren")
        ->assertRedirect();

    $copy = Package::where('name', 'Hochzeit Basis (Kopie)')->sole();

    expect($copy->description)->toBe('Ganztags')
        ->and($copy->items)->toHaveCount(2)
        ->and($copy->items->pluck('id')->intersect($original->items->pluck('id')))->toBeEmpty();

    $copy->items->first()->update(['title' => 'Anders']);

    expect($original->items()->first()->title)->not->toBe('Anders');
});

// Duplicated to be changed — the visibility call comes after.
it('keeps a deactivated package deactivated in its copy', function () {
    $original = Package::factory()->inactive()->create(['name' => 'Alt']);

    $this->actingAs(staffMember(), 'staff')->post("/pakete/{$original->public_id}/duplizieren");

    expect(Package::where('name', 'Alt (Kopie)')->sole()->is_active)->toBeFalse();
});

it('logs a duplicate as a package creation carrying its source', function () {
    $original = Package::factory()->create(['name' => 'Hochzeit Basis']);

    $this->actingAs(staffMember(), 'staff')->post("/pakete/{$original->public_id}/duplizieren");

    expect(AuditLog::where('event', 'package.created')->sole()->context['source'])->toBe('duplicate');
});

it('deletes a package, its items, and logs it — but keeps a lead that started from it', function () {
    $user = staffMember();
    $package = Package::factory()->has(PackageItem::factory()->count(2), 'items')->create(['name' => 'Hochzeit Basis']);
    $lead = Lead::factory()->create(['package_id' => $package->id]);

    $this->actingAs($user, 'staff')
        ->delete("/pakete/{$package->public_id}")
        ->assertRedirect('/pakete');

    expect(Package::find($package->id))->toBeNull()
        ->and(PackageItem::where('package_id', $package->id)->count())->toBe(0)
        ->and($lead->refresh()->package_id)->toBeNull();

    expect(AuditLog::where('event', 'package.deleted')->where('subject', $package->public_id)->sole()->context['name'])
        ->toBe('Hochzeit Basis');
});
