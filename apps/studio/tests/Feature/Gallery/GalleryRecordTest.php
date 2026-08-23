<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function managedGallery(): Gallery
{
    $gallery = Gallery::factory()->for(Project::factory())->create(['title' => 'Hochzeitstag']);
    $highlights = GallerySet::where('gallery_id', $gallery->id)->first();
    $trauung = GallerySet::factory()->for($gallery)->create(['name' => 'Trauung', 'position' => 1]);

    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $trauung->id,
        'key' => 'galleries/g1/a.jpg', 'filename' => 'a.jpg', 'width' => 4000, 'height' => 3000,
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $highlights->id,
        'key' => 'galleries/g1/b.jpg', 'filename' => 'b.jpg', 'width' => 4000, 'height' => 3000,
    ]);

    $gallery->publish();

    return $gallery->fresh();
}

it('lists the sets with their counts', function () {
    $gallery = managedGallery();

    $props = props("/galerien/{$gallery->public_id}");

    expect(collect($props['sets'])->pluck('name')->all())->toBe(['Highlights', 'Trauung'])
        ->and(collect($props['sets'])->firstWhere('name', 'Trauung')['count'])->toBe(1)
        ->and($props['figures']['assets'])->toBe(2);
});

// Fix round 1, Critical: a Failed row has no key, so it must never reach the
// picture grid — it has nothing a <img> or a set badge could show.
it('keeps a Failed asset out of the pictures, its set count, and the total', function () {
    $gallery = managedGallery();
    $highlights = GallerySet::where('gallery_id', $gallery->id)->where('name', 'Highlights')->first();

    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $highlights->id,
        'status' => AssetStatus::Failed, 'key' => null, 'filename' => 'c.jpg', 'error' => 'corrupt file',
    ]);

    $props = props("/galerien/{$gallery->public_id}");

    expect(collect($props['assets'])->pluck('filename'))->not->toContain('c.jpg')
        ->and(collect($props['sets'])->firstWhere('name', 'Highlights')['count'])->toBe(1)
        ->and($props['figures']['assets'])->toBe(2);
});

// Fix round 1, Important: figures.assets is what the studio counts pictures
// by; publish() is what a client actually receives. They must agree, or a
// Failed row makes the photographer chase a picture that was never there.
it('keeps figures.assets equal to what publishing actually snapshots', function () {
    $gallery = managedGallery();
    $highlights = GallerySet::where('gallery_id', $gallery->id)->where('name', 'Highlights')->first();

    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $highlights->id,
        'status' => AssetStatus::Failed, 'key' => null, 'filename' => 'c.jpg', 'error' => 'corrupt file',
    ]);

    $version = $gallery->fresh()->publish();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['figures']['assets'])->toBe(count($version->assets));
});

// Fix round 1, Important: the exact assetCount bug fixed on GalleryRowData,
// four lines above the siblings block in the same controller.
it('shows a sibling gallery its real picture count', function () {
    $project = Project::factory()->create();
    $gallery = Gallery::factory()->for($project)->create(['title' => 'Erste Galerie']);
    $sibling = Gallery::factory()->for($project)->create(['title' => 'Zweite Galerie']);
    $siblingSet = GallerySet::where('gallery_id', $sibling->id)->first();

    Asset::factory()->create(['gallery_id' => $sibling->id, 'gallery_set_id' => $siblingSet->id]);
    Asset::factory()->create(['gallery_id' => $sibling->id, 'gallery_set_id' => $siblingSet->id]);

    $props = props("/galerien/{$gallery->public_id}");

    expect(collect($props['siblings'])->firstWhere('id', $sibling->public_id)['assetCount'])->toBe(2);
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
