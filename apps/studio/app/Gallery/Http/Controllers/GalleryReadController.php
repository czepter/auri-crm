<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-08: the api surface's read side of a gallery — whatever Gallery::
 * currentVersion() resolves to right now, never the draft. Which version
 * that is can change the moment a studio owner republishes (Gallery::
 * publish()), so this response carries no-store: a guest's browser, and
 * anything between it and this process, must ask again on the next load
 * rather than serve an old version's asset list against a new session.
 *
 * R31/spec decision 26 is the one exception: a request carrying a valid
 * GalleryPreviewPass (checked first, below, so the version path a plain
 * guest hits is untouched by this branch's existence) gets the draft
 * instead — draft() is that path, kept separate rather than folded into
 * the version branch because it answers a structurally different question
 * (no GalleryVersion exists yet) with a structurally different response.
 *
 * The image bytes those assets point at are the opposite — see
 * GalleryImageController — because a GalleryVersion, once published, never
 * changes what it contains. A draft's bytes are the opposite of that too:
 * see GalleryDraftImageController.
 *
 * An external gallery (X-02) and a gallery nobody has published yet both
 * have no currentVersion, so both 404 here without special-casing either —
 * an external gallery has no draft either, for the same reason draft()
 * refuses it explicitly.
 *
 * A gallery carrying a view password never reaches this method at all —
 * `gallery.ungated` (EnsureUngatedGallery) refuses it one layer up, because
 * the api surface holds no session to check G-06's claim against.
 */
class GalleryReadController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery, GalleryPreviewPass $previewPass): JsonResponse
    {
        if ($previewPass->opens($gallery, $request->query('preview'))) {
            return $this->draft($gallery);
        }

        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        return response()->json([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $version->title,
                'draft' => false,
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

    /**
     * Gallery::draftAssets() in place of a GalleryVersion's frozen list —
     * the response keeps the same shape (a "version" object) so apps/
     * gallery does not need a second response type, but version.public_id
     * is null: there is no GalleryVersion to address image bytes by, which
     * is exactly why GalleryDraftImageController exists as its own route
     * rather than one this response points at with a borrowed id.
     *
     * A gallery with no pictures yet answers 200 with an empty asset list —
     * that is a real, expected draft state, unlike the 404 the published
     * path answers for the same emptiness (there, no currentVersion means
     * nobody has published anything to look at). An external gallery (X-02)
     * owns no draft to preview — its assets live wherever external_url
     * points — so it 404s here exactly as it does on the published path.
     */
    private function draft(Gallery $gallery): JsonResponse
    {
        abort_if($gallery->is_external, 404);

        return response()->json([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $gallery->title,
                'draft' => true,
                'version' => [
                    'public_id' => null,
                    'version' => null,
                    'published_at' => null,
                    'assets' => $gallery->draftAssets()
                        ->map(fn (array $asset) => [
                            'key' => $asset['key'],
                            'width' => $asset['width'],
                            'height' => $asset['height'],
                        ])
                        ->values(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
