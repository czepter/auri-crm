<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryWatermark;
use App\Models\StudioSettings;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    StudioSettings::query()->update(['gallery_home_mode' => 'overview']);
    StudioSettings::forgetCurrent();
    Storage::fake('s3');
});

/** @param array<string, mixed> $attributes */
function coverGallery(array $attributes = []): GalleryVersion
{
    foreach (['cover.jpg', 'other.jpg'] as $key) {
        Storage::disk('s3')->put("galleries/c/{$key}", syntheticJpeg(800, 500));
    }

    $gallery = Gallery::factory()->create(['listed_on_home' => true, ...$attributes]);

    return GalleryVersion::factory()->create([
        'gallery_id' => $gallery->id,
        'cover_key' => 'galleries/c/cover.jpg',
        'assets' => [
            ['key' => 'galleries/c/cover.jpg', 'set' => 'Highlights'],
            ['key' => 'galleries/c/other.jpg', 'set' => 'Highlights'],
        ],
    ]);
}

it('serves the cover unwatermarked while the image route keeps every key watermarked', function () {
    $version = coverGallery(['view_mode' => 'text', 'watermark_text' => 'Studio']);

    $response = test()->get("/api/gallery-home/covers/{$version->public_id}?w=800&fmt=jpeg")->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=300')
        ->and(Storage::disk('s3')->exists('variants/800/jpeg/galleries/c/cover.jpg.jpeg'))->toBeTrue();

    $mark = GalleryWatermark::for($version->gallery)->key();

    foreach (['cover.jpg', 'other.jpg'] as $key) {
        test()->get("/api/gallery-versions/{$version->public_id}/images/galleries/c/{$key}?w=800&fmt=jpeg")->assertOk();
        expect(Storage::disk('s3')->exists("variants/800/jpeg/{$mark}/galleries/c/{$key}.jpeg"))->toBeTrue();
    }

    expect(Storage::disk('s3')->exists('variants/800/jpeg/galleries/c/other.jpg.jpeg'))->toBeFalse();
    test()->get("/api/gallery-home/covers/{$version->public_id}/galleries/c/other.jpg?w=800&fmt=jpeg")->assertNotFound();
});

it('serves the cover of a password gallery without a pass while the image route stays closed', function () {
    $version = coverGallery();
    $version->gallery->rotateViewPassword('geheim');

    test()->get("/api/gallery-home/covers/{$version->public_id}?w=800&fmt=jpeg")->assertOk();

    foreach (['cover.jpg', 'other.jpg'] as $key) {
        test()->get("/api/gallery-versions/{$version->public_id}/images/galleries/c/{$key}?w=800&fmt=jpeg")->assertNotFound();
    }
});

it('denies the cover route', function (Closure $break, int $status) {
    $version = coverGallery();
    $break($version);

    test()->get("/api/gallery-home/covers/{$version->public_id}?w=".($status === 422 ? 500 : 800).'&fmt=jpeg')->assertStatus($status);
})->with([
    'unlisted' => [fn (GalleryVersion $v) => $v->gallery->update(['listed_on_home' => false]), 404],
    'previous version' => [fn (GalleryVersion $v) => GalleryVersion::factory()->create(['gallery_id' => $v->gallery_id, 'version' => 2]), 404],
    'mode none' => [fn () => StudioSettings::query()->update(['gallery_home_mode' => 'none']) && StudioSettings::forgetCurrent(), 404],
    'mode redirect' => [fn () => StudioSettings::query()->update(['gallery_home_mode' => 'redirect']) && StudioSettings::forgetCurrent(), 404],
    'disallowed width' => [fn () => null, 422],
]);
