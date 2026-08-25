<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\ImageVariant;
use App\Http\Controllers\Controller;
use App\Media\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * The grid's own image bytes before a gallery has ever published — the
     * one gap GalleryImageController leaves, since it is scoped to a
     * GalleryVersion and a draft has none. Same encode-and-cache as the
     * guest route (ImageVariant::deliver), same ownership guard as
     * original() above, load-bearing for the same reason.
     *
     * A Failed asset has no key to serve — 404 rather than a broken <img>,
     * since the pictures pane never renders one of those as a tile anyway.
     *
     * `private`, not `public`: unlike the guest route, reaching this one
     * requires a staff session, so a shared cache must not be allowed to
     * hand the bytes to anyone who later asks for the same URL. The bytes
     * themselves are as immutable as the guest route's — the asset's key
     * never changes for a given checksum — so the browser's own cache may
     * still keep them forever.
     */
    public function preview(Request $request, Gallery $gallery, Asset $asset): Response
    {
        $this->authorize('manageMedia', $gallery);

        abort_unless($asset->gallery_id === $gallery->id, 404);
        abort_if($asset->key === null, 404);

        $width = (int) $request->query('w');
        $format = (string) $request->query('fmt');

        abort_unless(ImageVariant::isAllowed($width, $format), 422);

        return response(ImageVariant::deliver($asset->key, $width, $format), 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
