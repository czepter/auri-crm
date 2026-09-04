<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\DraftDeviation;
use App\Media\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not see a design re-emitted in a different key order as a deviation', function () {
    $gallery = Gallery::factory()->create(['design' => ['cover' => 'full', 'type' => 'serif']]);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);
    $gallery->fresh()->publish();

    $fresh = $gallery->fresh();

    // Postgres jsonb already canonicalises key order on write, so round
    // tripping a reordered array through save() would silently launder the
    // very thing this test exists to catch. Setting the cast attribute
    // in memory, never saved, is what actually produces two arrays with
    // identical content and different key order for DraftDeviation to
    // compare.
    $fresh->design = array_reverse($fresh->currentVersion->design, true);

    expect(DraftDeviation::of($fresh))->toBeFalse();
});

it('still sees an actual design change as a deviation', function () {
    $gallery = Gallery::factory()->create(['design' => ['cover' => 'full', 'type' => 'serif']]);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg']);
    $gallery->fresh()->publish();

    $gallery->forceFill(['design' => ['cover' => 'full', 'type' => 'sans']])->save();

    expect(DraftDeviation::of($gallery->fresh()))->toBeTrue();
});
