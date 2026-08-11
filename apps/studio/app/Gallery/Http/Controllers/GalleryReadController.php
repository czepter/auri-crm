<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * G-08: the api surface's read side of a gallery — whatever Gallery::
 * currentVersion() resolves to right now, never the draft. Which version
 * that is can change the moment a studio owner republishes (Gallery::
 * publish()), so this response carries no-store: a guest's browser, and
 * anything between it and this process, must ask again on the next load
 * rather than serve an old version's asset list against a new session.
 *
 * The image bytes those assets point at are the opposite — see
 * GalleryImageController — because a GalleryVersion, once published, never
 * changes what it contains.
 *
 * An external gallery (X-02) and a gallery nobody has published yet both
 * have no currentVersion, so both 404 here without special-casing either.
 *
 * G-06 (#57) wraps this route with the actual access gate; until it lands,
 * reachability is assumed per G-08's own scope note.
 */
class GalleryReadController extends Controller
{
    public function __invoke(Gallery $gallery): JsonResponse
    {
        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        return response()->json([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $version->title,
                'version' => [
                    'public_id' => $version->public_id,
                    'version' => $version->version,
                    'published_at' => $version->published_at,
                    'assets' => collect($version->assets)
                        ->map(fn (array $asset) => [
                            'key' => $asset['key'],
                            'width' => $asset['width'] ?? null,
                            'height' => $asset['height'] ?? null,
                        ])
                        ->values(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
