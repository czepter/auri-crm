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

        return response($this->variant($key, $width, $format), 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            // Never purged, never invalidated — publishing a new version
            // changes which GalleryVersion a gallery's currentVersion
            // points at (see GalleryReadController), it never rewrites this
            // one's contents.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * G-01 (#229): the encoded bytes, from the disk if this width and format
     * have been asked for before.
     *
     * The lightbox's filmstrip is what made the missing cache visible — 33
     * previews at w=400, every one of them decoding a 40-megapixel original
     * and re-encoding it, on every open. Since the request path is already
     * immutable (a version's assets never change after publish, which is why
     * the response above may claim `immutable`), the answer to the same
     * request is a file, not a computation.
     *
     * No lock: two requests racing on the same variant both write the same
     * bytes to the same key.
     */
    private function variant(string $key, int $width, string $format): string
    {
        $disk = Storage::disk('s3');
        $cached = "variants/{$width}/{$format}/{$key}.{$format}";

        if ($disk->exists($cached)) {
            return $disk->get($cached);
        }

        abort_unless($disk->exists($key), 404);

        $variant = ImageVariant::resizeAndEncode($disk->get($key), $width, $format);
        $disk->put($cached, $variant);

        return $variant;
    }
}
