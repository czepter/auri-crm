<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Models\GalleryView;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GallerySecretGate;
use App\Gallery\Support\GalleryVisitor;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The gallery, gate and grid on one screen — P-08 (#227), mockups
 * galerie-gate.html and galerie.html.
 *
 * It was two pages before, and the second one led nowhere: after the password
 * this screen said "Bilderübersicht noch nicht verfügbar" while the real grid
 * sat on the Astro host that nothing linked to and that the api surface could
 * not gate at all (EnsureUngatedGallery). One page ends both problems — the
 * cover a visitor lands on is the cover they scroll past into the pictures,
 * and the pictures are served by the surface that holds the claim.
 *
 * Its own GET renders locked-or-unlocked itself rather than being wrapped by
 * EnsureGalleryGate, which would have nowhere to redirect a failing request
 * but here, forever.
 *
 * One screen, two audiences. The couple it belongs to and the eighty people
 * they sent the link to see the same photographs; below them the couple also
 * see the link they hand out, the gates in front of it, their own selections
 * and what their guests did. That was a second screen at /eure-auswahl until
 * it turned out the answer to "who is looking?" is one sentence, not a
 * routing decision — see GalleryVisitor.
 */
class GalleryPasswordController extends Controller
{
    public function __construct(private readonly GalleryVisitor $visitor) {}

    public function show(Request $request, Gallery $gallery, GalleryAccessSession $claims): Response
    {
        // G-14: withdrawn closes this entry point too — it is the one this
        // whole screen exists behind, so a link that still resolves the
        // model must not still resolve the gate.
        abort_if($gallery->isWithdrawn(), 404);

        $unlocked = $claims->viewGranted($gallery);
        $version = $unlocked ? $gallery->currentVersion : null;

        if ($unlocked) {
            $this->countView($request, $gallery);
        }

        $isClient = $this->visitor->isClient($gallery);

        return Inertia::render('Gallery/Show', [
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'date' => $gallery->shoot?->date?->format('Y-m-d'),
                'venue' => $gallery->shoot?->venue(),
                'expiresAt' => $gallery->expires_at?->toIso8601String(),
                'url' => url("/galerien/{$gallery->public_id}"),
            ],
            'unlocked' => $unlocked,
            // A button for something the gallery does not offer is a 403 with
            // extra steps — the routes already refuse it (gallery.feature:),
            // this is so the screen never draws it.
            'offers' => [
                'downloads' => $gallery->downloads_enabled,
                'lists' => $gallery->lists_enabled,
                'comments' => $gallery->comments_enabled,
            ],
            // Null while locked, and null for a gallery nobody has published
            // yet — an unlocked gate over an empty gallery is a real state,
            // not an error, so the screen says so rather than 404ing a link
            // the studio has already sent out.
            'assets' => $version === null ? null : $this->assets($version),
            // P-06 (#225): on the tile, a list menu replaces the single
            // heart as soon as a client is signed in. A guest keeps the
            // heart — which is the same thing, a list without a name.
            'lists' => $isClient ? $this->lists($gallery, $version) : null,
            // G-02 (#230): the filter bar counts a guest's hearts, which are
            // AssetFavorite rows on this session rather than a list of their
            // own. Null for a client — theirs is the unnamed list above.
            'favorited' => $isClient || $version === null ? null : AssetFavorite::query()
                ->where('gallery_version_id', $version->id)
                ->where('guest_identifier', $this->visitor->guestId())
                ->pluck('asset_key')
                ->all(),
            // Everything below belongs to the couple alone. Absent for a
            // guest rather than rendered empty: a guest has no link of their
            // own to share and no gates to rotate.
            'gates' => $isClient ? [
                'viewPassword' => $gallery->requiresViewPassword(),
                'downloadPin' => $gallery->requiresDownloadPin(),
                'emailVerification' => $gallery->requiresVerifiedEmail(),
            ] : null,
            'guests' => $isClient && $version !== null ? [
                'views' => (int) $gallery->view_count,
                'marks' => AssetFavorite::query()->where('gallery_version_id', $version->id)->count(),
                'comments' => AssetComment::query()->where('gallery_version_id', $version->id)->count(),
            ] : null,
        ]);
    }

    /**
     * $gallery arrives as the raw public_id, not an implicit-bound model:
     * route-model binding 404s before this method ever runs, and a plain 404
     * here would tell a guest guessing passwords "that id doesn't exist" —
     * exactly the enumeration G-07 closes. Resolving it by hand instead lets
     * a nonexistent gallery fall through the same uniform-failure path as a
     * wrong password on a real one — GallerySecretGate counts and refuses a
     * missing gallery exactly like a wrong guess against a real one.
     */
    public function store(Request $request, string $gallery, GalleryAccessSession $claims, GallerySecretGate $gate): RedirectResponse
    {
        $model = Gallery::findByPublicId($gallery);

        // G-07/G-14: a withdrawn gallery must be indistinguishable from one
        // that never existed here — unlike show(), this is the uniform-
        // failure path, and a distinct 404 would let a garbage password
        // enumerate withdrawn ids. Falling through with $model nulled reuses
        // the exact same "nonexistent" branch below rather than a second one
        // that could drift from it.
        if ($model?->isWithdrawn()) {
            $model = null;
        }

        $requiresPassword = $model === null || $model->requiresViewPassword();

        if ($requiresPassword) {
            $validated = $request->validate(['password' => ['required', 'string']]);

            $granted = $gate->attempt($request->ip(), $gallery, $model === null ? null : $model->checkViewPassword(...), $validated['password']);

            if (! $granted || $model === null) {
                return back()->withErrors(['password' => __('gallery.password_wrong')]);
            }
        }

        $claims->grant($model, GalleryAccessSession::VIEW, $model->view_password_version);

        return redirect("/galerien/{$model->public_id}");
    }

    /**
     * The client's own selections, with enough of each to show a strip. Keys
     * the current version no longer has are left out rather than rendered as
     * broken pictures — a re-publish that dropped one shortens the list, it
     * does not break it.
     */
    private function lists(Gallery $gallery, ?GalleryVersion $version): array
    {
        $known = collect($version?->assets ?? [])->pluck('key');

        return $this->visitor->lists($gallery)->map(function (GalleryList $list) use ($version, $known) {
            $keys = $list->items->pluck('asset_key')->intersect($known)->values();

            return [
                // The heart answers to "herz" rather than to its own id, so
                // the tile menu works before the row behind it exists.
                'id' => $list->name === null ? 'herz' : $list->public_id,
                'listId' => $list->public_id,
                'name' => $list->name,
                'keys' => $keys->all(),
                'covers' => $version === null ? [] : $keys->take(8)
                    ->map(fn (string $key) => "/galerie-bilder/{$version->public_id}/{$key}")
                    ->values()->all(),
                'changedAt' => $list->updated_at->toIso8601String(),
            ];
        })->all();
    }

    /**
     * C-21 (#256): "Aufgerufen", as a row rather than a counter.
     *
     * Once per visitor per day, which is the folding DownloadEvent already
     * uses: a couple showing their parents the pictures on a laptop is one
     * visit, not forty. The unique index enforces it, so two tabs opening at
     * once cannot write two rows — the second collides and is swallowed here
     * rather than becoming a 500 in front of a guest.
     *
     * galleries.view_count stays and keeps being incremented: it is what the
     * couple's own screen shows, and it counts what happened before this
     * table existed.
     */
    private function countView(Request $request, Gallery $gallery): void
    {
        $seen = "gallery_viewed.{$gallery->public_id}";

        if (! $request->session()->has($seen)) {
            $request->session()->put($seen, true);
            $gallery->increment('view_count');
        }

        try {
            GalleryView::query()->insertOrIgnore([
                'tenant_id' => config('tenancy.tenant_id'),
                'gallery_id' => $gallery->id,
                'contact_id' => $this->visitor->client($gallery)?->id,
                'visitor_identifier' => $this->visitor->guestId(),
                'viewed_on' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two tabs on the same second. The row exists, which is the whole
            // point of the write.
        }
    }

    /**
     * The published version's asset list, each pointing at this surface's own
     * image route rather than the api surface's — the claim that unlocked
     * this page lives in this session, and an <img> on another host would not
     * carry it.
     */
    private function assets(GalleryVersion $version): array
    {
        return collect($version->assets)
            ->map(fn (array $asset) => [
                'key' => $asset['key'],
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
                'src' => "/galerie-bilder/{$version->public_id}/{$asset['key']}",
            ])
            ->values()
            ->all();
    }
}
