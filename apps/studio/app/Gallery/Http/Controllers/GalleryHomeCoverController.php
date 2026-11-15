<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Http\Controllers\Controller;
use App\Media\Support\ImageVariant;
use App\Models\StudioSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The one picture of a listed gallery that is public: no key in the URL, the
 * server picks it (Gallery::homeCoverKey) and serves it without a watermark.
 * Every other image keeps its watermark and password gate on GalleryImageController.
 */
class GalleryHomeCoverController extends Controller
{
    public function __invoke(Request $request, GalleryVersion $galleryVersion): Response
    {
        $width = (int) $request->query('w');
        $format = (string) $request->query('fmt');

        abort_unless(ImageVariant::isAllowed($width, $format), 422);
        abort_unless(StudioSettings::current()->gallery_home_mode === 'overview', 404);

        $gallery = Gallery::query()->listedOnHome()->whereKey($galleryVersion->gallery_id)->first();
        abort_unless($gallery !== null && $gallery->currentVersion?->is($galleryVersion), 404);

        $key = $gallery->homeCoverKey($galleryVersion);
        abort_if($key === null, 404);

        return response(ImageVariant::deliver($key, $width, $format, null), 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
