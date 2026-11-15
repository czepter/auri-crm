<?php

use App\Enums\StaffRole;
use App\Gallery\Models\GalleryPreset;
use App\Gallery\Support\GallerySettings;
use App\Models\StudioSettings;
use App\Models\User;
use App\Settings\Actions\UpdateStudioSetting;
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
            ->where('settings.downloadRule', 'open')
            ->where('settings.design.cover', 'zentriert')
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
        'gallery_default_download_rule' => 'after_payment',
        'gallery_default_lists_enabled' => true,
        'gallery_default_comments_enabled' => false,
        'gallery_default_design' => ['cover' => 'links', 'type' => 'serif', 'colour' => 'dunkel', 'size' => 'klein', 'spacing' => 'eng', 'flow' => 'zeilen'],
    ])->assertRedirect('/galerien');

    expect(GallerySettings::design())
        ->toBe(['cover' => 'links', 'type' => 'serif', 'colour' => 'dunkel', 'size' => 'klein', 'spacing' => 'eng', 'flow' => 'zeilen']);
});

it('refuses a half design', function () {
    $this->patch('/einstellungen/galerien', [
        'gallery_default_download_rule' => 'after_payment',
        'gallery_default_lists_enabled' => true,
        'gallery_default_comments_enabled' => true,
        'gallery_default_design' => ['colour' => 'dunkel'],
    ])->assertSessionHasErrors('gallery_default_design.cover');
});

it('falls back to the built-in design until the studio says otherwise', function () {
    expect(GallerySettings::design())->toBe(GalleryPreset::DESIGN);
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

it('stores a valid redirect and refuses a missing, foreign-scheme or own-host url', function (?string $url, ?string $error) {
    config(['services.gallery.url' => 'http://gallery.test']);
    $response = $this->patch('/einstellungen/galerien', ['gallery_home_mode' => 'redirect', 'gallery_home_redirect_url' => $url]);

    $error === null
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['gallery_home_redirect_url' => $error]);

    expect(StudioSettings::current()->gallery_home_mode)->toBe($error === null ? 'redirect' : 'none');
})->with([
    'valid' => ['https://example.test/galerien', null],
    'missing' => [null, 'Pflichtfeld'],
    'scheme' => ['ftp://example.test', 'Nur http- und https-Adressen'],
    'own host' => ['http://gallery.test/x', 'Zeigt auf gallery.test selbst'],
]);

it('keeps the homepage mode from a role without ManageSettings', function () {
    $this->actingAs(User::factory()->withMfa()->create(['role' => StaffRole::Manager]), 'staff');

    $this->patch('/einstellungen/galerien', ['gallery_home_mode' => 'overview'])->assertNotFound();
});
