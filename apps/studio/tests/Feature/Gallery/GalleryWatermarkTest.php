<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Media\Support\ImageVariant;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function watermarkable(array $attributes = []): array
{
    Storage::fake('s3');
    $image = imagecreatetruecolor(800, 500);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 140, 160));
    ob_start();
    imagejpeg($image);
    $original = ob_get_clean();
    Storage::disk('s3')->put('galleries/w/a.jpg', $original);

    $gallery = Gallery::factory()->create($attributes);
    Asset::factory()->create([
        'gallery_id' => $gallery->id,
        'gallery_set_id' => GallerySet::where('gallery_id', $gallery->id)->first()->id,
        'key' => 'galleries/w/a.jpg', 'filename' => 'a.jpg', 'width' => 800, 'height' => 500,
    ]);

    return [$gallery->fresh()->publish(), $original];
}

const WATERMARK_URL = '/api/gallery-versions/%s/images/galleries/w/a.jpg?w=800&fmt=jpeg';

it('lays the text over the delivered preview and leaves original and clean variant alone', function () {
    [$version, $original] = watermarkable(['view_mode' => 'text', 'watermark_text' => 'Studio Rahn']);

    $marked = $this->get(sprintf(WATERMARK_URL, $version->public_id))->assertOk()->getContent();
    $clean = ImageVariant::deliver('galleries/w/a.jpg', 800, 'jpeg');

    expect($marked)->not->toBe($clean)
        ->and(Storage::disk('s3')->get('galleries/w/a.jpg'))->toBe($original)
        ->and($clean)->toBe(ImageVariant::resizeAndEncode($original, 800, 'jpeg'));
});

it('delivers the plain variant when no watermark is set', function () {
    [$version] = watermarkable(['view_mode' => 'none']);

    $body = $this->get(sprintf(WATERMARK_URL, $version->public_id))->assertOk()->getContent();

    expect($body)->toBe(ImageVariant::deliver('galleries/w/a.jpg', 800, 'jpeg'));
});

it('keeps one cached variant per watermark text', function () {
    [$version] = watermarkable(['view_mode' => 'text', 'watermark_text' => 'Eins']);
    $this->get(sprintf(WATERMARK_URL, $version->public_id))->assertOk();
    $version->gallery->update(['watermark_text' => 'Zwei']);
    $this->get(sprintf(WATERMARK_URL, $version->public_id))->assertOk();

    expect(collect(Storage::disk('s3')->allFiles('variants/800/jpeg'))->filter(fn ($file) => preg_match('#^variants/800/jpeg/[0-9a-f]{16}/#', $file)))->toHaveCount(2);
});

it('refuses the images of a locked gallery', function () {
    [$version] = watermarkable(['view_mode' => 'locked']);

    $this->get(sprintf(WATERMARK_URL, $version->public_id))->assertForbidden();
});
