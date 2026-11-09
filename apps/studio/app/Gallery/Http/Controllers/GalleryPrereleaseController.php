<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\StartGalleryPrerelease;
use App\Gallery\Actions\StopGalleryPrerelease;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * R32/R39: the More menu's "Pre-release" — shares a gallery's address
 * before it has anything published. Authorised against `publish`
 * (ManagePortalAccess), not `manageMedia`: this makes the gallery's title
 * reachable by anyone holding the link, the same judgment GalleryPolicy
 * already makes for who may decide a gallery is presentable outside the
 * studio (see the policy's own doc comment on that ability).
 */
class GalleryPrereleaseController extends Controller
{
    public function store(Request $request, Gallery $gallery, StartGalleryPrerelease $start): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $validated = $request->validate(['asset_ids' => ['nullable', 'array'], 'asset_ids.*' => ['string']]);

        $own = $request->boolean('own');
        $started = $start($gallery, auth('staff')->user(), $own, $own ? ($validated['asset_ids'] ?? null) : null);

        return $own ? redirect("/galerien/{$started->public_id}") : back();
    }

    public function destroy(Gallery $gallery, StopGalleryPrerelease $stop): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $stop($gallery, auth('staff')->user());

        return back();
    }
}
