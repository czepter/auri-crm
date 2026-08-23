<?php

use App\Enums\StaffRole;
use App\Gallery\Models\GalleryPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create(['role' => StaffRole::Owner]);
    $this->actingAs($this->staff, 'staff');
});

it('creates a preset', function () {
    $this->post('/einstellungen/galerie-vorlagen', [
        'name' => 'Hochzeit',
        'expiry_mode' => 'days',
        'expiry_days' => 365,
        'requires_settled_balance' => true,
        'downloads_enabled' => true,
        'lists_enabled' => null,
        'comments_enabled' => false,
        'design' => ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'],
    ])->assertRedirect('/einstellungen/galerie-vorlagen');

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
        'requires_settled_balance' => null,
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
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Assistant]), 'staff');

    $this->get('/einstellungen/galerie-vorlagen')->assertNotFound();
});
