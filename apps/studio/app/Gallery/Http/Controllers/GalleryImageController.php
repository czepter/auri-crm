<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\ImageVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * G-08: the only way an image's bytes leave the `s3` disk — the browser
 * never gets a bucket URL or a presigned one (G-01a's docs/media.md,
 * "Delivery"). Scoped to one GalleryVersion, not to the Gallery it belongs
 * to, because a version's assets never change after publish() writes them
 * (GalleryVersion's own doc comment): the same {version}/{key}/{w}/{fmt}
 * request always decodes the same bytes, forever, which is what lets the
 * response below claim `immutable` rather than merely `max-age`.
 *
 * $key must appear in that exact version's own assets array — not just
 * exist on the disk — so this route can never be turned into a generic
 * "fetch anything from the bucket by path" proxy for a tenant's other
 * galleries.
 */
class GalleryImageController extends Controller
{
    public function __invoke(Request $request, GalleryVersion $galleryVersion, string $key): Response
    {
        $width = (int) $request->query('w');
        $format = (string) $request->query('fmt');

        abort_unless(ImageVariant::isAllowed($width, $format), 422);

        $known = collect($galleryVersion->assets)->contains(fn (array $asset) => $asset['key'] === $key);
        abort_unless($known, 404);

        abort_unless(Storage::disk('s3')->exists($key), 404);

        $variant = ImageVariant::resizeAndEncode(Storage::disk('s3')->get($key), $width, $format);

        return response($variant, 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            // Never purged, never invalidated — publishing a new version
            // changes which GalleryVersion a gallery's currentVersion
            // points at (see GalleryPasswordController), it never rewrites this
            // one's contents.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
