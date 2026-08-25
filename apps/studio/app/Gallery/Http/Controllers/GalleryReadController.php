<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Gallery\Support\GalleryViewPass;
use App\Gallery\Support\GuardsGalleryViewPassword;
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
 * GalleryPreviewPass gets the draft instead — draft() is that path, kept
 * separate rather than folded into the version branch because it answers a
 * structurally different question (no GalleryVersion exists yet) with a
 * structurally different response.
 *
 * R37: a preview pass must open the draft even when the gallery also
 * carries a view password — the whole point of a preview link is to reach
 * a couple before the gallery is published, and a studio very often sets
 * the password at creation, before there is anything to publish. Requiring
 * both would mean sending the finished gallery's own key alongside a link
 * meant to precede it. So this route sits outside `gallery.pass`
 * (routes/api.php) and checks GalleryPreviewPass itself — and, once that
 * fails, falls through to exactly the same withdrawal-and-password check
 * EnsureGalleryPass applies to every other route in that group
 * (GuardsGalleryViewPassword, shared with it rather than copied), so a
 * guest with no preview token sees this route behave exactly as if it were
 * still inside the middleware group.
 *
 * R39/R40: pre-release sits ahead of both. The order below —
 * withdrawal, pre-release, preview pass, password — is a ladder of "who is
 * allowed to see more than the last rung", each one closing off before the
 * next is even asked:
 *   1. withdrawn always 404s, no exception (R39, "closed beats being
 *      prepared") — checked first so nothing below can override it.
 *   2. pre-release, once active, answers with the teaser response and
 *      nothing else — including to a request carrying an otherwise-valid
 *      preview pass. R40 draws the line at "no protected content behind
 *      this link, ever"; a preview pass exists to hand specific people real
 *      draft photographs (R31), which is exactly what pre-release promises
 *      the general address will not do. A studio wanting to show someone
 *      the draft while pre-release is up takes the gallery out of
 *      pre-release first, rather than this route silently picking a
 *      winner between two "before publish" states.
 *   3. preview pass, R37's already-established exception to the password.
 *   4. the ordinary password-gated version path.
 * The next state added here should read this list and say which rung it
 * occupies, rather than guessing at an interaction with the others.
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
 */
class GalleryReadController extends Controller
{
    use GuardsGalleryViewPassword;

    public function __invoke(Request $request, Gallery $gallery, GalleryPreviewPass $previewPass, GalleryViewPass $viewPass): JsonResponse
    {
        // G-14/R39: withdrawal wins over every other state below, pre-release
        // included — see this class's own doc comment for the full order.
        abort_if($gallery->isWithdrawn(), 404);

        if ($gallery->isInPrerelease()) {
            return $this->prerelease($gallery);
        }

        // is_string(), not a raw query('preview'): see GalleryDraftImageController's
        // own comment on why an array here must not reach opens() raw.
        $preview = $request->query('preview');
        if ($previewPass->opens($gallery, is_string($preview) ? $preview : null)) {
            return $this->draft($gallery);
        }

        $this->abortUnlessViewable($gallery, $request, $viewPass);

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
     * R40: the whole response. Title and public_id, so apps/gallery can
     * render a heading and post the signup back to the right gallery — and
     * nothing else. No asset list, no counts, no cover, no expiry: this
     * link is handed out on purpose and there is nothing behind it to
     * protect, but that is exactly why it must never grow a field "because
     * the shape expects it" — every field added here is a field a
     * deliberately-public address can now show.
     */
    private function prerelease(Gallery $gallery): JsonResponse
    {
        return response()->json([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $gallery->title,
                'prerelease' => true,
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
