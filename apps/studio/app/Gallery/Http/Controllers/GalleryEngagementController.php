<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\NotifyStudioOfEngagement;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\AssetCommented;
use App\Gallery\Notifications\AssetFavorited;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-09's heart and G-12's comment rail, on the surface that serves the
 * pictures (P-08, #227).
 *
 * These lived on the api surface because apps/gallery rendered the grid from
 * another origin, and AssetFavoriteController's own doc comment spelled out
 * what that cost: no session reached it, so a guest's identity had to be a
 * string the browser minted into localStorage and sent along. Same-origin,
 * that whole argument goes away — the guest identity is the portal session
 * this request already carries, which is also the session the view gate's
 * claim lives in.
 *
 * Everything here sits behind `gallery.gate:view`: somebody who has not got
 * past the front door has nothing to favourite.
 */
class GalleryEngagementController extends Controller
{
    private const GUEST_KEY = 'gallery_guest_id';

    public function __construct(private readonly NotifyStudioOfEngagement $notifyStudio) {}

    /** This visitor's own hearts on the published version, plus every comment on it. */
    public function index(Request $request, Gallery $gallery): JsonResponse
    {
        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        return response()->json([
            'favorited' => AssetFavorite::query()
                ->where('gallery_version_id', $version->id)
                ->where('guest_identifier', $this->guestId($request))
                ->pluck('asset_key')
                ->values(),
            'comments' => AssetComment::query()
                ->where('gallery_version_id', $version->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn (AssetComment $comment) => [
                    'key' => $comment->asset_key,
                    'body' => $comment->body,
                    'createdAt' => $comment->created_at->toIso8601String(),
                ])
                ->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function toggleFavorite(Request $request, Gallery $gallery): JsonResponse
    {
        $version = $this->versionWithAsset($gallery, $key = $this->assetKey($request));
        $guestId = $this->guestId($request);

        $existing = AssetFavorite::query()
            ->where('gallery_version_id', $version->id)
            ->where('asset_key', $key)
            ->where('guest_identifier', $guestId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            // Removing one is not news: the studio hears when a couple marks
            // a picture, not when they change their mind — same rule the api
            // surface's own toggle followed.
            return response()->json(['favorited' => false]);
        }

        try {
            $favorite = AssetFavorite::create([
                'gallery_version_id' => $version->id,
                'asset_key' => $key,
                'guest_identifier' => $guestId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two clicks racing each other. The index is what decides, and
            // the answer either way is that the picture is marked.
            return response()->json(['favorited' => true]);
        }

        ($this->notifyStudio)(new AssetFavorited($gallery, $favorite));

        return response()->json(['favorited' => true]);
    }

    public function storeComment(Request $request, Gallery $gallery): JsonResponse
    {
        $version = $this->versionWithAsset($gallery, $key = $this->assetKey($request));

        $body = $request->validate(['body' => ['required', 'string', 'max:2000']])['body'];

        $comment = AssetComment::create([
            'gallery_version_id' => $version->id,
            'asset_key' => $key,
            'guest_identifier' => $this->guestId($request),
            'body' => $body,
        ]);

        ($this->notifyStudio)(new AssetCommented($gallery, $comment));

        return response()->json([
            'comment' => [
                'key' => $comment->asset_key,
                'body' => $comment->body,
                'createdAt' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Who this visitor is, for the length of their session and no longer.
     *
     * A signed-in client never gets here for their own hearts — theirs are a
     * GalleryList owned by the Contact (P-06, #225), because a string that
     * dies with a cookie cannot carry "Eure Auswahl" across two devices. This
     * is the guest's, and a guest is exactly somebody with no account.
     */
    private function guestId(Request $request): string
    {
        $existing = $request->session()->get(self::GUEST_KEY);

        if ($existing !== null) {
            return $existing;
        }

        $request->session()->put(self::GUEST_KEY, $minted = bin2hex(random_bytes(16)));

        return $minted;
    }

    private function assetKey(Request $request): string
    {
        return $request->validate(['key' => ['required', 'string']])['key'];
    }

    /** $key must be in the published version's own assets — same rule GalleryImageController follows. */
    private function versionWithAsset(Gallery $gallery, string $key)
    {
        $version = $gallery->currentVersion;

        abort_if($version === null, 404);
        abort_unless(
            collect($version->assets)->contains(fn (array $asset) => $asset['key'] === $key),
            404,
        );

        return $version;
    }
}
