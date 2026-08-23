<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function managedGallery(): Gallery
{
    $gallery = Gallery::factory()->for(Project::factory())->create([
        'title' => 'Hochzeitstag',
        'assets' => [
            ['key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000, 'set' => 'Trauung'],
            ['key' => 'galleries/g1/b.jpg', 'width' => 4000, 'height' => 3000],
        ],
    ]);
    $gallery->publish();

    return $gallery->fresh();
}

// C-19 (#255): sets are a key on the asset entry, so they travel with the
// picture and land in the version snapshot for free.
it('groups the pictures into sets, with the unnamed ones in their own group', function () {
    $gallery = managedGallery();

    $props = props("/galerien/{$gallery->public_id}");

    expect(collect($props['sets'])->pluck('name')->all())->toContain('Trauung')
        ->and(collect($props['sets'])->firstWhere('name', null)['count'])->toBe(1)
        ->and($props['figures']['assets'])->toBe(2);
});

// C-17 (#253) stored them recoverably so this screen could show them.
it('shows the two secrets in clear and sets them where they are read', function () {
    $gallery = managedGallery();
    $gallery->rotateViewPassword('basthorst2026');

    expect(props("/galerien/{$gallery->public_id}")['gallery']['viewPassword'])->toBe('basthorst2026');

    test()->patch("/galerien/{$gallery->public_id}", ['view_password' => 'winsen2027'])->assertRedirect();

    expect($gallery->fresh()->view_password)->toBe('winsen2027')
        // Rotating turns out every guest already inside — the version bump is
        // the whole reason the setter exists.
        ->and($gallery->fresh()->view_password_version)->toBeGreaterThan(1);
});

it('keeps the look on the gallery, and leaves it null until somebody chooses', function () {
    $gallery = managedGallery();

    expect(props("/galerien/{$gallery->public_id}")['gallery']['design'])->toBeNull();

    test()->patch("/galerien/{$gallery->public_id}", ['design' => ['cover' => 'split', 'colour' => 'dark']])
        ->assertRedirect();

    expect($gallery->fresh()->design)->toBe(['cover' => 'split', 'colour' => 'dark']);
});

// C-20 (#257): three sheets, because the questions differ.
it('carries the three activity sheets', function () {
    $gallery = managedGallery();

    expect(props("/galerien/{$gallery->public_id}")['activity'])
        ->toHaveKeys(['downloads', 'marks', 'comments', 'views']);
});

it('sets the three offers from the permissions tab', function () {
    $gallery = managedGallery();

    expect(props("/galerien/{$gallery->public_id}")['gallery']['offers']['downloads'])->toBeTrue();

    test()->patch("/galerien/{$gallery->public_id}", ['downloads_enabled' => false])->assertRedirect();

    expect($gallery->fresh()->downloads_enabled)->toBeFalse()
        // Untouched keys stay untouched: the tab saves one row at a time.
        ->and($gallery->fresh()->lists_enabled)->toBeTrue();
});
