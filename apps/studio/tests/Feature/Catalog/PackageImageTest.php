<?php

use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(staffMember(StaffRole::Owner), 'staff');
});

it('stores a block picture under the tenant’s own catalogue folder', function () {
    $package = Package::factory()->create();
    $item = PackageItem::factory()->for($package)->create();

    $this->post("/pakete/{$package->public_id}/positionen/{$item->public_id}/bild", [
        'image' => UploadedFile::fake()->image('album.png'),
    ])->assertRedirect();

    $path = $item->fresh()->image_path;

    expect($path)->toStartWith("catalog/{$package->tenant_id}/");
    Storage::disk('local')->assertExists($path);
});

// Der Befund aus der Sicherheitspruefung: ein Pfad aus dem Formular waere das
// Lesen jeder Datei auf dem Disk, ueber eine Route, die das Bild ausliefert.
it('refuses to take a stored path from the form', function () {
    Storage::disk('local')->put('contracts/secret.pdf', '%PDF-geheim');

    $package = Package::factory()->create();
    $item = PackageItem::factory()->for($package)->create();

    $this->patch("/pakete/{$package->public_id}", [
        'name' => $package->name,
        'items' => [[
            'id' => $item->public_id,
            'title' => $item->title,
            'quantity' => 1,
            'unit_price' => 1000,
            'image_path' => 'contracts/secret.pdf',
        ]],
    ])->assertRedirect();

    expect(Package::query()->sole()->items()->sole()->image_path)->toBeNull();
});

it('keeps the picture across a save that replaces every row', function () {
    $package = Package::factory()->create();
    $item = PackageItem::factory()->for($package)->create();

    $this->post("/pakete/{$package->public_id}/positionen/{$item->public_id}/bild", [
        'image' => UploadedFile::fake()->image('album.png'),
    ]);

    $path = $item->fresh()->image_path;

    $this->patch("/pakete/{$package->public_id}", [
        'name' => 'Umbenannt',
        'items' => [[
            'id' => $item->public_id,
            'title' => 'Album',
            'quantity' => 1,
            'unit_price' => 40_000,
        ]],
    ])->assertRedirect();

    expect(Package::query()->sole()->items()->sole()->image_path)->toBe($path);
});
