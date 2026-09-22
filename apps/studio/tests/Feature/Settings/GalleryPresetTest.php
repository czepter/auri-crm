<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    $this->actingAs($this->staff, 'staff');
});

it('creates a preset', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Hochzeit',
        'expiry_mode' => 'days',
        'expiry_days' => 365,
        'downloads_enabled' => true,
        'lists_enabled' => null,
        'comments_enabled' => false,
        'design' => ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'],
    ])->assertRedirect();

    $preset = GalleryPreset::sole();

    expect($preset->name)->toBe('Hochzeit')
        ->and($preset->expiry_days)->toBe(365)
        // Dreiwertig: was die Vorlage offen lässt, überschreibt nichts.
        ->and($preset->lists_enabled)->toBeNull()
        ->and($preset->comments_enabled)->toBeFalse();
});

it('renames and deletes one', function () {
    $preset = GalleryPreset::factory()->create(['name' => 'Portrait']);

    $this->patch("/einstellungen/galerie-vorlagen/{$preset->public_id}", [
        'name' => 'Business',
        'expiry_mode' => 'inherit',
        'expiry_days' => null,
        'downloads_enabled' => null,
        'lists_enabled' => null,
        'comments_enabled' => null,
        'design' => null,
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('Business');

    $this->delete("/einstellungen/galerie-vorlagen/{$preset->public_id}")->assertRedirect();

    expect(GalleryPreset::count())->toBe(0);
});

it('refuses a mode the resolution does not know', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Kaputt',
        'expiry_mode' => 'irgendwann',
    ])->assertSessionHasErrors('expiry_mode');
});

// Der 404-statt-403-Riegel, den AuthorizesStaffSettings für jeden
// Einstellungsscreen setzt.
it('answers 404 for a role without ManageSettings', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff');

    $this->get('/vorlagen?typ=galerien')->assertNotFound();
});

// Pixieset kann die Einstellungen einer Galerie als Vorlage sichern. Die
// Galerie traegt ein absolutes Ablaufdatum, die Vorlage eine Spanne — was die
// Galerie mitbekommen hat, ist der Abstand zwischen Anlage und Ablauf.
it('saves a gallery as a named preset', function () {
    $gallery = Gallery::factory()->create([
        'design' => ['cover' => 'split', 'type' => 'serif', 'colour' => 'dark', 'grid' => 'dense'],
        'downloads_enabled' => false,
        'lists_enabled' => true,
        'comments_enabled' => false,
        'created_at' => now()->subDays(5),
        'expires_at' => now()->addDays(360),
    ]);

    $this->post("/galerien/{$gallery->public_id}/vorlage", ['name' => 'Hochzeit'])
        ->assertRedirect();

    $preset = GalleryPreset::sole();

    expect($preset->name)->toBe('Hochzeit')
        ->and($preset->design['colour'])->toBe('dark')
        ->and($preset->downloads_enabled)->toBeFalse()
        ->and($preset->comments_enabled)->toBeFalse()
        ->and($preset->expiry_mode)->toBe('days')
        ->and($preset->expiry_days)->toBe(365);
});

it('says never for a gallery that does not expire', function () {
    $gallery = Gallery::factory()->create(['expires_at' => null]);

    $this->post("/galerien/{$gallery->public_id}/vorlage", ['name' => 'Ohne Ablauf']);

    expect(GalleryPreset::sole()->expiry_mode)->toBe('never')
        ->and(GalleryPreset::sole()->expiry_days)->toBeNull();
});

it('refuses to save a gallery as a preset without ManageSettings', function () {
    $gallery = Gallery::factory()->create();
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff');

    $this->post("/galerien/{$gallery->public_id}/vorlage", ['name' => 'Nein'])->assertNotFound();

    expect(GalleryPreset::count())->toBe(0);
});

// Ganz oder gar nicht: null heisst Studio-Wert, ein Objekt heisst, die Vorlage
// legt das Aussehen fest.
it('keeps a preset design set and clearable', function () {
    $preset = GalleryPreset::factory()->create();

    $this->patch("/einstellungen/galerie-vorlagen/{$preset->public_id}", [
        'name' => 'Dunkel',
        'expiry_mode' => 'inherit',
        'design' => ['cover' => 'split', 'type' => 'serif', 'colour' => 'dark', 'grid' => 'dense'],
    ])->assertRedirect();

    expect($preset->fresh()->design['colour'])->toBe('dark');

    $this->patch("/einstellungen/galerie-vorlagen/{$preset->public_id}", [
        'name' => 'Dunkel',
        'expiry_mode' => 'inherit',
        'design' => null,
    ])->assertRedirect();

    expect($preset->fresh()->design)->toBeNull();
});

// "Vorlage anwenden" (More menu): the reverse direction from birthValues() —
// an existing gallery already carries real values, so a preset field left
// null (inherit) must leave the gallery's own value untouched rather than
// falling back to a studio default that has no rung to fall through to here.
it('applies a preset\'s design and offers to an existing gallery', function () {
    $gallery = Gallery::factory()->create([
        'design' => ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'],
        'downloads_enabled' => true,
        'lists_enabled' => true,
        'comments_enabled' => true,
    ]);
    $preset = GalleryPreset::factory()->create([
        'design' => ['cover' => 'split', 'type' => 'sans', 'colour' => 'dark', 'grid' => 'dense'],
        'downloads_enabled' => false,
        'lists_enabled' => null,
        'comments_enabled' => null,
    ]);

    $this->post("/galerien/{$gallery->public_id}/vorlage-anwenden", ['gallery_preset_id' => $preset->public_id])
        ->assertRedirect();

    $fresh = $gallery->fresh();
    expect($fresh->design['colour'])->toBe('dark')
        ->and($fresh->downloads_enabled)->toBeFalse()
        // Left null (inherit) on the preset — the gallery's own value survives.
        ->and($fresh->lists_enabled)->toBeTrue()
        ->and($fresh->comments_enabled)->toBeTrue();
});

it('refuses to apply a preset without manageMedia', function () {
    $gallery = Gallery::factory()->create();
    $preset = GalleryPreset::factory()->create();
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Contributor]), 'staff');

    $this->post("/galerien/{$gallery->public_id}/vorlage-anwenden", ['gallery_preset_id' => $preset->public_id])
        ->assertForbidden();
});
