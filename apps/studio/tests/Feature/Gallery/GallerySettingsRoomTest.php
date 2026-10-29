<?php

use App\Enums\StaffRole;
use App\Gallery\Models\GalleryPreset;
use App\Models\User;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Admin]), 'staff');
});

it('ships the defaults and the presets with the list', function () {
    GalleryPreset::factory()->create(['name' => 'Hochzeit', 'is_default' => true]);

    $this->get('/galerien')
        ->assertInertia(fn ($page) => $page
            ->component('Galleries/Index')
            ->where('settings.downloadsEnabled', true)
            ->where('settings.design.cover', 'full')
            ->where('presets.0.isDefault', true));
});

it('keeps the defaults from a role that may not edit them', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Manager]), 'staff');

    $this->get('/galerien')
        ->assertInertia(fn ($page) => $page->where('settings', null)->where('presets', []));
});

it('writes the design the room chose', function () {
    $this->patch('/einstellungen/galerien', [
        'gallery_default_expiry_days' => 90,
        'gallery_default_downloads_enabled' => true,
        'gallery_default_lists_enabled' => true,
        'gallery_default_comments_enabled' => false,
        'gallery_default_design' => ['cover' => 'plain', 'type' => 'serif', 'colour' => 'dark', 'grid' => 'dense'],
    ])->assertRedirect('/galerien');

    expect(app(StudioSettingsResolver::class)->galleryDesign())
        ->toBe(['cover' => 'plain', 'type' => 'serif', 'colour' => 'dark', 'grid' => 'dense']);
});

it('refuses a half design', function () {
    $this->patch('/einstellungen/galerien', [
        'gallery_default_downloads_enabled' => true,
        'gallery_default_lists_enabled' => true,
        'gallery_default_comments_enabled' => true,
        'gallery_default_design' => ['colour' => 'dark'],
    ])->assertSessionHasErrors('gallery_default_design.cover');
});

it('falls back to the built-in design until the studio says otherwise', function () {
    expect(app(StudioSettingsResolver::class)->galleryDesign())->toBe(GalleryPreset::DESIGN);
});

it('moves the start mark to the preset that was chosen', function () {
    $first = GalleryPreset::factory()->create(['is_default' => true]);
    $second = GalleryPreset::factory()->create();

    $this->patch("/einstellungen/galerie-vorlagen/{$second->public_id}/standard")->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('ships the studio design to the create screen', function () {
    app(UpdateStudioSetting::class)('gallery_default_design', [
        'cover' => 'split', 'type' => 'serif', 'colour' => 'warm', 'grid' => 'single',
    ]);

    $this->get('/galerien/neu')
        ->assertInertia(fn ($page) => $page->where('studioDefaults.design.colour', 'warm'));
});
