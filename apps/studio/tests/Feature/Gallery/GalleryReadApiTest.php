<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

it('404s a gallery nobody has published yet', function () {
    $gallery = Gallery::factory()->create();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('404s an external gallery, which owns no version to serve', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://legacy.example/g/1']);

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('404s an unknown public_id rather than leaking whether it ever existed', function () {
    $this->getJson('/api/galleries/nonexistent-cuid')->assertNotFound();
});

it('serves the currently-published version, not the draft, as no-store', function () {
    $gallery = Gallery::factory()->create(['title' => 'Draft title']);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    $asset = Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'filename' => 'a.jpg', 'width' => 4000, 'height' => 3000]);
    $version = $gallery->fresh()->publish();

    // Edit-after-publish must not move what this endpoint returns.
    $gallery->update(['title' => 'Edited after publish']);
    $asset->update(['removed_from_draft_at' => now()]);

    $response = $this->getJson("/api/galleries/{$gallery->public_id}")->assertOk();

    $response->assertJson([
        'gallery' => [
            'public_id' => $gallery->public_id,
            'title' => 'Draft title',
            'version' => [
                'public_id' => $version->public_id,
                'version' => 1,
                'assets' => [
                    ['key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000],
                ],
            ],
        ],
    ]);

    // Symfony's ResponseHeaderBag normalises Cache-Control (adds `private`
    // when nothing marks the response public) — the directive that matters
    // here is `no-store` itself, not the exact string it ends up composed
    // into.
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('serves the latest version after a republish', function () {
    $gallery = Gallery::factory()->create(['title' => 'v1', 'assets' => []]);
    $gallery->publish();

    $gallery->update(['title' => 'v2', 'assets' => [['key' => 'galleries/g1/b.jpg', 'width' => 100, 'height' => 100]]]);
    $v2 = $gallery->publish();

    $this->getJson("/api/galleries/{$gallery->public_id}")
        ->assertOk()
        ->assertJson(['gallery' => ['title' => 'v2', 'version' => ['public_id' => $v2->public_id, 'version' => 2]]]);
});
