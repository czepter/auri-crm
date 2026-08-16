<?php

use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function galleryWithViewPassword(string $key = 'galleries/g1/a.jpg'): Gallery
{
    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => $key, 'width' => 200, 'height' => 100],
    ]]);
    $gallery->publish();
    $gallery->rotateViewPassword('basthorst2026');

    return $gallery->fresh();
}

it('refuses the asset list of a gallery behind a view password', function () {
    $gallery = galleryWithViewPassword();

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertNotFound();
});

it('refuses the image bytes of a gallery behind a view password', function () {
    $version = galleryWithViewPassword()->currentVersion;

    $this->get("/api/gallery-versions/{$version->public_id}/images/galleries/g1/a.jpg?w=800&fmt=jpeg")
        ->assertNotFound();
});

it('refuses favorites and comments on a gallery behind a view password', function () {
    $version = galleryWithViewPassword()->currentVersion;

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites")->assertNotFound();
    $this->getJson("/api/gallery-versions/{$version->public_id}/comments")->assertNotFound();
});

it('serves a gallery that carries no view password', function () {
    $gallery = galleryWithViewPassword();
    $gallery->rotateViewPassword(null);

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertOk();
});

it('ignores the download pin, which gates downloads rather than viewing', function () {
    $gallery = galleryWithViewPassword();
    $gallery->rotateViewPassword(null);
    $gallery->rotateDownloadPin('4821');

    $this->getJson("/api/galleries/{$gallery->public_id}")->assertOk();
});
