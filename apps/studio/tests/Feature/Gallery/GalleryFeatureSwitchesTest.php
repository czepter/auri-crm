<?php

use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('starts every gallery with all three offers open', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->downloads_enabled)->toBeTrue()
        ->and($gallery->lists_enabled)->toBeTrue()
        ->and($gallery->comments_enabled)->toBeTrue();
});

describe('on the portal surface', function () {
    beforeEach(fn () => useSurface(Surface::PORTAL));

    it('refuses the download screen when the gallery offers no download', function () {
        $gallery = Gallery::factory()->create(['downloads_enabled' => false]);

        $this->get("/galerien/{$gallery->public_id}/download")->assertForbidden();
    });

    it('still serves the download screen when the gallery offers one', function () {
        $gallery = Gallery::factory()->create(['downloads_enabled' => true]);

        $this->get("/galerien/{$gallery->public_id}/download")->assertOk();
    });

    it('refuses a mark when the gallery offers no lists', function () {
        $gallery = Gallery::factory()->create(['lists_enabled' => false]);

        $this->post("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g1/a.jpg'])
            ->assertForbidden();
    });

    it('refuses a comment when the gallery offers no comments', function () {
        $gallery = Gallery::factory()->create(['comments_enabled' => false]);

        $this->post("/galerien/{$gallery->public_id}/kommentar", [
            'key' => 'galleries/g1/a.jpg',
            'body' => 'Schön',
        ])->assertForbidden();
    });
});

describe('on the api surface', function () {
    beforeEach(fn () => useSurface(Surface::API));

    // The Astro viewer is the second surface the same switch has to cover —
    // a gallery closed on the portal that stays open here is not closed.
    it('refuses a mark from the viewer when the gallery offers no lists', function () {
        $gallery = Gallery::factory()->create([
            'lists_enabled' => false,
            'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
        ]);
        $version = $gallery->publish();

        $this->postJson(
            "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
            ['guest_id' => 'guest-1'],
        )->assertForbidden();
    });

    it('refuses a comment from the viewer when the gallery offers no comments', function () {
        $gallery = Gallery::factory()->create([
            'comments_enabled' => false,
            'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
        ]);
        $version = $gallery->publish();

        $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
            'asset_key' => 'galleries/g1/a.jpg',
            'body' => 'Schön',
            'guest_id' => 'guest-1',
        ])->assertForbidden();
    });
});
