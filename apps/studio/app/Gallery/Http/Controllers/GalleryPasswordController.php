<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GallerySecretGate;
use App\Http\Controllers\Controller;
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
 */
class GalleryPasswordController extends Controller
{
    public function show(Request $request, Gallery $gallery, GalleryAccessSession $claims): Response
    {
        $unlocked = $claims->viewGranted($gallery);
        $version = $unlocked ? $gallery->currentVersion : null;

        if ($unlocked) {
            $this->countView($request, $gallery);
        }

        return Inertia::render('Gallery/Show', [
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'date' => $gallery->shoot?->date?->format('Y-m-d'),
                'venue' => $gallery->shoot?->venue(),
                'expiresAt' => $gallery->expires_at?->toIso8601String(),
            ],
            'unlocked' => $unlocked,
            // Null while locked, and null for a gallery nobody has published
            // yet — an unlocked gate over an empty gallery is a real state,
            // not an error, so the screen says so rather than 404ing a link
            // the studio has already sent out.
            'assets' => $version === null ? null : $this->assets($version),
            // P-06 (#225): on the tile, a list menu replaces the single
            // heart as soon as a client is signed in. A guest keeps the
            // heart — which is the same thing, a list without a name.
            'lists' => $this->listsFor($gallery),
        ]);
    }

    /**
     * $gallery arrives as the raw public_id, not an implicit-bound model:
     * route-model binding 404s before this method ever runs, and a plain 404
     * here would tell a guest guessing passwords "that id doesn't exist" —
     * exactly the enumeration G-07 closes. Resolving it by hand instead lets
     * a nonexistent gallery fall through the same uniform-failure path as a
     * wrong password on a real one, all the way down to GallerySecretGate's
     * dummy hash check.
     */
    public function store(Request $request, string $gallery, GalleryAccessSession $claims, GallerySecretGate $gate): RedirectResponse
    {
        $model = Gallery::findByPublicId($gallery);
        $requiresPassword = $model === null || $model->requiresViewPassword();

        if ($requiresPassword) {
            $validated = $request->validate(['password' => ['required', 'string']]);

            $granted = $gate->attempt($request->ip(), $gallery, $model?->view_password_hash, $validated['password']);

            if (! $granted || $model === null) {
                return back()->withErrors(['password' => __('gallery.password_wrong')]);
            }
        }

        $claims->grant($model, GalleryAccessSession::VIEW, $model->view_password_version);

        return redirect("/galerien/{$model->public_id}");
    }

    /**
     * This client's own selections, or null for a guest. Owned by the
     * Contact rather than by a localStorage string, which is the whole
     * reason GalleryList exists beside AssetFavorite — see its docblock.
     */
    private function listsFor(Gallery $gallery): ?array
    {
        $contact = auth('portal')->user();

        if ($contact === null || $gallery->project_id === null
            || ! $contact->visibleProjects()->whereKey($gallery->project_id)->exists()) {
            return null;
        }

        return GalleryList::query()
            ->where('gallery_id', $gallery->id)
            ->where('contact_id', $contact->id)
            ->with('items')
            ->get()
            ->sortBy(fn (GalleryList $list) => [$list->name === null ? 0 : 1, $list->id])
            ->map(fn (GalleryList $list) => [
                'id' => $list->name === null ? 'herz' : $list->public_id,
                'name' => $list->name,
                'keys' => $list->items->pluck('asset_key')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * P-06 (#225): "Aufrufe". Once per visitor session rather than per
     * request — a couple showing their parents the pictures on a laptop is
     * one visit, not forty, and DownloadEvent has only ever counted
     * downloads. increment() rather than a read-modify-write, so two guests
     * arriving at once do not lose one of the two.
     */
    private function countView(Request $request, Gallery $gallery): void
    {
        $seen = "gallery_viewed.{$gallery->public_id}";

        if ($request->session()->has($seen)) {
            return;
        }

        $request->session()->put($seen, true);
        $gallery->increment('view_count');
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
