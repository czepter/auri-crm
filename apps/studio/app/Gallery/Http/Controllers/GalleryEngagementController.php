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
 * The api surface keeps its own pair for apps/gallery, which renders from
 * another origin: no session reaches it, so a guest's identity there has to
 * be a string the browser minted into localStorage — see
 * AssetFavoriteController's own doc comment for why. Same-origin, that whole
 * argument goes away, so this one uses the portal session the request
 * already carries, which is also where the view gate's claim lives.
 *
 * Both write the same AssetFavorite and AssetComment rows, so a gallery
 * opened either way counts the same in P-06's "Eure Gäste".
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
                ->with('contact')
                ->orderBy('created_at')
                ->get()
                ->map($this->row(...))
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
                'contact_id' => $this->contactId($gallery),
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
            'contact_id' => $this->contactId($gallery),
            'body' => $body,
        ]);

        ($this->notifyStudio)(new AssetCommented($gallery, $comment));

        // The exact shape index() returns, so the rail can append it without
        // a reload — a row that came back missing its author rendered as a
        // nameless comment until the next fetch.
        return response()->json(['comment' => $this->row($comment)], 201);
    }

    /** @return array<string, mixed> */
    private function row(AssetComment $comment): array
    {
        return [
            'key' => $comment->asset_key,
            'body' => $comment->body,
            // G-01 (#229): a client writes under their own name, a guest as
            // "Gast". One screen, one rule, no second row shape.
            'author' => $comment->contact?->name ?? __('gallery.guest'),
            'own' => $comment->contact_id !== null && $comment->contact_id === auth('portal')->id(),
            'createdAt' => $comment->created_at->toIso8601String(),
        ];
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

    /**
     * The signed-in client this gallery belongs to, or null. Checked against
     * the booking rather than taken from the guard alone: somebody signed in
     * to their own portal who then opens a link a friend forwarded them is a
     * guest on that gallery, and should read as one.
     */
    private function contactId(Gallery $gallery): ?int
    {
        $contact = auth('portal')->user();

        if ($contact === null || $gallery->project_id === null) {
            return null;
        }

        return $contact->visibleProjects()->whereKey($gallery->project_id)->exists()
            ? $contact->id
            : null;
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
