<?php

use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** A tiny real image, so imagecreatefromstring() has something to decode. */
function fakeOriginalBytes(): string
{
    $image = imagecreatetruecolor(200, 100);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 60));
    ob_start();
    imagejpeg($image);

    return ob_get_clean();
}

function publishedVersionWithAsset(string $key = 'galleries/g1/a.jpg')
{
    Storage::fake('s3');
    Storage::disk('s3')->put($key, fakeOriginalBytes());

    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => $key, 'width' => 200, 'height' => 100],
    ]]);

    return $gallery->publish();
}

it('rejects a width outside the allowed set', function () {
    $version = publishedVersionWithAsset();

    $this->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=999&fmt=webp")
        ->assertStatus(422);
});

it('rejects a format outside the allowed set', function () {
    $version = publishedVersionWithAsset();

    $this->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=400&fmt=gif")
        ->assertStatus(422);
});

it('404s a key that is not in this version\'s own assets, even if it exists on the disk', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/g1/not-listed.jpg', fakeOriginalBytes());

    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100],
    ]]);
    $version = $gallery->publish();

    $this->get("/galerie-bilder/{$version->public_id}/galleries/g1/not-listed.jpg?w=400&fmt=webp")
        ->assertNotFound();
});

it('streams a resized webp variant with an immutable cache header', function () {
    $version = publishedVersionWithAsset();

    $response = $this->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=400&fmt=webp");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/webp');

    // Symfony's ResponseHeaderBag reorders Cache-Control directives when it
    // composes the header, so assert on the directives themselves.
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public')
        ->toContain('immutable')
        ->toContain('max-age=31536000');

    $decoded = getimagesizefromstring($response->getContent());
    expect($decoded[0])->toBe(200); // the 200x100 source is narrower than the 400 requested — never upscaled
});

it('streams an avif variant', function () {
    $version = publishedVersionWithAsset();

    $response = $this->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=400&fmt=avif");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/avif');
});

it('keeps serving an old version\'s image after a republish, unchanged', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/g1/a.jpg', fakeOriginalBytes());

    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100],
    ]]);
    $v1 = $gallery->publish();

    $gallery->update(['assets' => []]);
    $gallery->publish();

    $this->get("/galerie-bilder/{$v1->public_id}/galleries/g1/a.jpg?w=400&fmt=webp")
        ->assertOk();
});
