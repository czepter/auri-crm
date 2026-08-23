<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryAssetController extends Controller
{
    /**
     * Foreign ids are silently dropped, not refused: $gallery->assetRows()
     * already scopes the update to this gallery, so an id from another
     * gallery matches no row and simply does nothing — the same outcome an
     * explicit refusal would produce for the caller, without a second query
     * to tell the two cases apart.
     */
    public function move(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_ids' => ['required', 'array'],
            'asset_ids.*' => ['string'],
            'gallery_set_id' => ['required', 'string'],
        ]);

        $set = $gallery->sets()->where('public_id', $validated['gallery_set_id'])->firstOrFail();

        $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])
            ->update(['gallery_set_id' => $set->id]);

        return back();
    }

    /**
     * Nimmt das Bild aus dem Entwurf, nicht aus der Welt: Zeile und Objekt
     * bleiben, weil BuildGalleryZip die Keys bereits veröffentlichter
     * Versionen über genau diese Zeilen auflöst.
     */
    public function remove(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_ids' => ['required', 'array'],
            'asset_ids.*' => ['string'],
        ]);

        $removed = $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])->get();

        $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])
            ->update(['removed_from_draft_at' => now()]);

        if ($removed->contains(fn (Asset $asset) => $asset->id === $gallery->cover_asset_id)) {
            $gallery->update(['cover_asset_id' => null, 'cover_focal_x' => null, 'cover_focal_y' => null]);
        }

        return back();
    }

    public function cover(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_id' => ['required', 'string'],
            'focal_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'focal_y' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $asset = $gallery->assetRows()->inDraft()->where('public_id', $validated['asset_id'])->firstOrFail();
        $changed = $asset->id !== $gallery->cover_asset_id;

        $gallery->update([
            'cover_asset_id' => $asset->id,
            // Ein Fokuspunkt gehört zu einem Bild; auf einem anderen zeigt er ins Leere.
            'cover_focal_x' => $changed ? ($validated['focal_x'] ?? null) : ($validated['focal_x'] ?? $gallery->cover_focal_x),
            'cover_focal_y' => $changed ? ($validated['focal_y'] ?? null) : ($validated['focal_y'] ?? $gallery->cover_focal_y),
        ]);

        return back();
    }

    /**
     * A short-lived signed S3 URL, never the object itself — otherwise this
     * process buffers whatever the original is, RAW files included.
     *
     * {asset} binds globally by public_id (no scopeBindings() in this app),
     * so the ownership check below is load-bearing: without it, a
     * manageMedia grant on any gallery would reach any other gallery's
     * asset through this route.
     */
    public function original(Gallery $gallery, Asset $asset): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        abort_unless($asset->gallery_id === $gallery->id, 404);

        return redirect()->away(Storage::disk('s3')->temporaryUrl($asset->key, now()->addMinutes(5)));
    }
}
