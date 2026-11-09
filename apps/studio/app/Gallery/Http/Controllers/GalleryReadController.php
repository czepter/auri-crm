<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Support\GalleryDesign;
use App\Gallery\Support\GalleryDownloadGate;
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
 * R43 (corrects R39/R40): the order below — withdrawal, preview pass,
 * pre-release, password — is a ladder of "who is allowed to see more than
 * the last rung", each one closing off before the next is even asked:
 *   1. withdrawn always 404s, no exception (R39, "closed beats being
 *      prepared") — checked first so nothing below can override it. R38
 *      already revokes every outstanding preview pass the moment a gallery
 *      is withdrawn, so a token that still verifies past this line cannot
 *      exist — withdrawal does not need its own case in the preview-pass
 *      branch to win.
 *   2. preview pass. An explicit credential — issued deliberately, pinned
 *      to one gallery, expiring, revocable — beats pre-release, which is
 *      only that gallery's public default. A preview-token holder is
 *      already entitled to the draft (that is the entire purpose of the
 *      token); pre-release was never protecting anything from them. R39's
 *      original ordering put pre-release first on the reasoning that both
 *      are "before publish" — too coarse: one is a state, the other is a
 *      key, and a specific key beats a default or it is not a key.
 *   3. pre-release, once active, answers with the teaser response and
 *      nothing else for every request that reaches this rung — no preview
 *      pass, no assets, nothing R40 does not name.
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
        // G-14/R39: withdrawal wins over every other state below — see this
        // class's own doc comment for the full order.
        abort_if($gallery->isWithdrawn(), 404);

        // R43: a preview pass outranks pre-release — a specific credential
        // beats that gallery's public default. is_string(), not a raw
        // query('preview'): see GalleryDraftImageController's own comment
        // on why an array here must not reach opens() raw.
        $preview = $request->query('preview');
        if ($previewPass->opens($gallery, is_string($preview) ? $preview : null)) {
            return $this->draft($gallery);
        }

        if ($gallery->isInPrerelease()) {
            return $this->prerelease($gallery);
        }

        $this->abortUnlessViewable($gallery, $request, $viewPass);

        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        return response()->json([
            'gallery' => [
                'public_id' => $gallery->public_id,
                'title' => $version->title,
                'draft' => false,
                'design' => GalleryDesign::resolve($version->design),
                'cover' => $gallery->coverFor($version->cover_key),
                ...$this->audience($gallery),
                'version' => [
                    'public_id' => $version->public_id,
                    'version' => $version->version,
                    'published_at' => $version->published_at,
                    'assets' => collect($gallery->view_mode === 'locked' ? [] : $version->assetsFor(false))
                        ->map(fn (array $asset) => [
                            'key' => $asset['key'],
                            'set' => $asset['set'] ?? null,
                            'width' => $asset['width'] ?? null,
                            'height' => $asset['height'] ?? null,
                        ])
                        ->values(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Was ein Gast hier darf und welche Sets er ansprechen kann. Der Link
     * zu einem Set trägt dessen public_id.
     *
     * @return array<string, mixed>
     */
    private function audience(Gallery $gallery): array
    {
        return [
            'permissions' => $gallery->permissionsFor(false),
            'intro' => $gallery->intro_enabled,
            'locked' => $gallery->view_mode === 'locked',
            'downloads' => GalleryDownloadGate::allows($gallery),
            'downloadAfterPayment' => $gallery->download_rule === 'after_payment' && ! GalleryDownloadGate::allows($gallery),
            'sets' => $gallery->sets()->where('access', GallerySet::GUESTS)->get()
                ->map(fn (GallerySet $set) => ['id' => $set->public_id, 'name' => $set->name])
                ->values(),
        ];
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
                'design' => GalleryDesign::resolve($gallery->design),
                'cover' => $gallery->coverFor($gallery->coverAsset?->key),
                ...$this->audience($gallery),
                'version' => [
                    'public_id' => null,
                    'version' => null,
                    'published_at' => null,
                    'assets' => $gallery->draftAssets()
                        ->map(fn (array $asset) => [
                            'key' => $asset['key'],
                            'set' => $asset['set'],
                            'width' => $asset['width'],
                            'height' => $asset['height'],
                        ])
                        ->values(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
