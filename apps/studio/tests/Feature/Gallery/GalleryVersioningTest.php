<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a gallery with a nullable shoot_id', function () {
    $project = Project::factory()->create();

    $projectWide = Gallery::factory()->for($project)->create();
    $perShoot = Gallery::factory()->for($project)->for(Shoot::factory()->for($project), 'shoot')->create();

    expect($projectWide->shoot_id)->toBeNull()
        ->and($perShoot->shoot_id)->not->toBeNull();
});

it('has no current version before the first publish', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->currentVersion)->toBeNull()
        ->and($gallery->versions()->count())->toBe(0);
});

it('publishing produces an immutable version 1', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a1', 'filename' => 'a1']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a2', 'filename' => 'a2']);

    $v1 = $gallery->fresh()->publish();

    expect($v1)->toBeInstanceOf(GalleryVersion::class)
        ->and($v1->version)->toBe(1)
        ->and($v1->title)->toBe('Verlobung')
        ->and(collect($v1->assets)->pluck('key')->all())->toBe(['a1', 'a2'])
        ->and($v1->published_at)->not->toBeNull();

    expect($gallery->currentVersion()->first()->id)->toBe($v1->id);
});

it('editing the draft afterwards requires publishing a new version to change what is served', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a1', 'filename' => 'a1']);

    $v1 = $gallery->fresh()->publish();

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a2', 'filename' => 'a2']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a3', 'filename' => 'a3']);
    $gallery->update(['title' => 'Verlobung (korrigiert)']);

    // The draft changed, but nothing published moved — v1 still exists, and
    // it is still what a caller reading "the current version" would get.
    $v1->refresh();
    expect($v1->title)->toBe('Verlobung')
        ->and(collect($v1->assets)->pluck('key')->all())->toBe(['a1'])
        ->and($gallery->currentVersion()->first()->id)->toBe($v1->id);
});

it('publishing again creates a new version and leaves the previous one serving its exact original contents', function () {
    $gallery = Gallery::factory()->create(['title' => 'Verlobung']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a1', 'filename' => 'a1']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a2', 'filename' => 'a2']);

    $v1 = $gallery->fresh()->publish();

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'a3', 'filename' => 'a3']);
    $gallery->update(['title' => 'Verlobung (korrigiert)']);
    $v2 = $gallery->fresh()->publish();

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->title)->toBe('Verlobung (korrigiert)')
        ->and(collect($v2->assets)->pluck('key')->all())->toBe(['a1', 'a2', 'a3']);

    // v1's exact original snapshot — untouched by the edit, untouched by v2.
    $v1->refresh();
    expect($v1->version)->toBe(1)
        ->and($v1->title)->toBe('Verlobung')
        ->and(collect($v1->assets)->pluck('key')->all())->toBe(['a1', 'a2']);

    expect($gallery->currentVersion()->first()->id)->toBe($v2->id);
    expect(collect(GalleryVersion::findByPublicId($v1->public_id)->assets)->pluck('key')->all())->toBe(['a1', 'a2']);
});

it('numbers versions off the gallery maximum, not off a counter that could be raced', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();
    $gallery->publish();
    $v3 = $gallery->publish();

    expect($v3->version)->toBe(3)
        ->and($gallery->versions()->count())->toBe(3);
});

it('scopes versions to their own gallery, not to every gallery in the family of the migration pattern', function () {
    $a = Gallery::factory()->create();
    $b = Gallery::factory()->create();

    $a->publish();
    $a->publish();
    $b->publish();

    expect($a->versions()->count())->toBe(2)
        ->and($b->versions()->count())->toBe(1)
        ->and($b->currentVersion->version)->toBe(1);
});

it('snapshots the rows, the design and the cover', function () {
    $gallery = Gallery::factory()->create(['design' => ['cover' => 'stripe', 'colour' => 'dark', 'grid' => 'dense', 'font' => null]]);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $first = Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg', 'taken_at' => '2026-08-22 10:00:00',
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg', 'taken_at' => '2026-08-22 09:00:00',
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg', 'removed_from_draft_at' => now(),
    ]);

    $gallery->update(['cover_asset_id' => $first->id]);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())
        ->toBe(['galleries/g/b.jpg', 'galleries/g/a.jpg'])
        ->and(collect($version->assets)->pluck('set')->unique()->all())->toBe(['Highlights'])
        ->and($version->cover_key)->toBe('galleries/g/a.jpg')
        ->and($version->design['colour'])->toBe('dark');
});


it('excludes a Failed asset from the published snapshot', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'status' => AssetStatus::Failed, 'key' => null, 'filename' => 'b.jpg', 'error' => 'corrupt file',
    ]);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())->toBe(['galleries/g/a.jpg']);
});

// Insert order (c, a, b) matches neither the expected result nor filename
// order — a fallback to created_at or to Postgres heap order would pass
// this test by accident if it also happened to match, so the seed order is
// deliberately not alphabetical and not the result order either.
it('sorts a set by its own rule with null taken_at last and filename as the tiebreak', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg', 'taken_at' => '2026-08-22 10:00:00']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg']);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())
        ->toBe(['galleries/g/a.jpg', 'galleries/g/b.jpg', 'galleries/g/c.jpg']);
});

it("honors a set's sort_dir of desc", function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    $set->update(['sort_dir' => 'desc']);

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg', 'taken_at' => '2026-08-22 09:00:00']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg', 'taken_at' => '2026-08-22 10:00:00']);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())->toBe(['galleries/g/b.jpg', 'galleries/g/a.jpg']);
});

it('orders sets by position before ordering the pictures within each one', function () {
    $gallery = Gallery::factory()->create();
    $highlights = GallerySet::where('gallery_id', $gallery->id)->first();
    $highlights->update(['position' => 1]);
    $portraits = GallerySet::factory()->create(['gallery_id' => $gallery->id, 'name' => 'Portraits', 'position' => 0]);

    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $highlights->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $portraits->id, 'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg']);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())->toBe(['galleries/g/b.jpg', 'galleries/g/a.jpg'])
        ->and(collect($version->assets)->pluck('set')->all())->toBe(['Portraits', 'Highlights']);
});
