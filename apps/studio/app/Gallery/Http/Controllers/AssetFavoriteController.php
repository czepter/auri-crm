<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\GalleryVersion;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-09: the lightbox's favorite toggle. A gallery guest has no account, and
 * none of G-06's three gate claims reach this surface anyway — they live in
 * the portal surface's own session (GalleryAccessSession's own doc comment),
 * which the api surface's route group never starts (routes/api.php uses the
 * framework's stock `api` middleware group: no cookies, no session). So
 * guest_id here is not that claim, and not a server-issued cookie either —
 * apps/gallery's lightbox module (src/lib/favorites.ts) mints one into
 * localStorage on first favorite and sends it with every call after. A
 * cookie minted by *this* app would be cross-site from that browser's point
 * of view (a different origin from apps/gallery's own), which only works
 * sent credentialed, which only works `SameSite=None; Secure` — real
 * machinery this repo has none of yet, for an identifier that protects
 * nothing worth protecting: forging someone else's guest_id only lets you
 * toggle a heart icon on their behalf, never see or change anything they can.
 *
 * $key must appear in the version's own assets array, same rule and same
 * reason as GalleryImageController's.
 */
class AssetFavoriteController extends Controller
{
    /** The current guest's favorited asset keys within one version. */
    public function index(Request $request, GalleryVersion $galleryVersion): JsonResponse
    {
        $guestId = (string) $request->query('guest_id', '');

        $keys = $guestId === ''
            ? collect()
            : AssetFavorite::query()
                ->where('gallery_version_id', $galleryVersion->id)
                ->where('guest_identifier', $guestId)
                ->pluck('asset_key');

        return response()->json(['favorited' => $keys->values()]);
    }

    public function toggle(Request $request, GalleryVersion $galleryVersion, string $key): JsonResponse
    {
        $validated = $request->validate(['guest_id' => ['required', 'string', 'max:64']]);

        $known = collect($galleryVersion->assets)->contains(fn (array $asset) => $asset['key'] === $key);
        abort_unless($known, 404);

        $favorite = AssetFavorite::query()
            ->where('gallery_version_id', $galleryVersion->id)
            ->where('asset_key', $key)
            ->where('guest_identifier', $validated['guest_id'])
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['favorited' => false]);
        }

        try {
            AssetFavorite::create([
                'gallery_version_id' => $galleryVersion->id,
                'asset_key' => $key,
                'guest_identifier' => $validated['guest_id'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A second request for the same guest+asset landed between the
            // lookup above and this insert — the unique index already made
            // the mark this one wanted to make, so the outcome is the same.
        }

        return response()->json(['favorited' => true]);
    }
}
