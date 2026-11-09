<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\NotifyStudioOfEngagement;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\AssetFavorited;
use App\Gallery\Support\IdentifiesGalleryGuest;
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
    use IdentifiesGalleryGuest;

    public function __construct(private readonly NotifyStudioOfEngagement $notifyStudio) {}

    /** The current guest's favorited asset keys within one version. */
    public function index(Request $request, GalleryVersion $galleryVersion): JsonResponse
    {
        $me = $this->guest($request, $galleryVersion);

        $keys = $me === null
            ? collect()
            : AssetFavorite::query()
                ->where('gallery_version_id', $galleryVersion->id)
                ->where('guest_identifier', $me->identifier)
                ->pluck('asset_key');

        return response()->json(['favorited' => $keys->values()]);
    }

    public function toggle(Request $request, GalleryVersion $galleryVersion, string $key): JsonResponse
    {
        // Kein Herz ohne Absender. Wer es setzt, soll es auf dem naechsten
        // Geraet wiederfinden — und das geht nur ueber eine bestaetigte
        // Adresse.
        $me = $this->guest($request, $galleryVersion);
        abort_if($me === null, 403);

        abort_unless($galleryVersion->sees($key, false), 404);

        $favorite = AssetFavorite::query()
            ->where('gallery_version_id', $galleryVersion->id)
            ->where('asset_key', $key)
            ->where('guest_identifier', $me->identifier)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['favorited' => false]);
        }

        try {
            $created = AssetFavorite::create([
                'gallery_version_id' => $galleryVersion->id,
                'asset_key' => $key,
                'guest_identifier' => $me->identifier,
            ]);

            // G-12: reaches the studio through the unread stream — only on
            // the add, never on the remove branch above (un-marking a
            // favorite is not itself an event worth surfacing).
            ($this->notifyStudio)(new AssetFavorited($galleryVersion->gallery, $created));
        } catch (UniqueConstraintViolationException) {
            // A second request for the same guest+asset landed between the
            // lookup above and this insert — the unique index already made
            // the mark this one wanted to make (and its own request already
            // notified the studio), so the outcome is the same.
        }

        return response()->json(['favorited' => true]);
    }
}
