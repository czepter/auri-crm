<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\NotifyStudioOfEngagement;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\AssetCommented;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-12: the lightbox's comment rail (mockup 10, `.cmts`/`.lbwrite`) — same
 * guest identity as G-09's AssetFavoriteController, and the same reason: no
 * account behind guest_id, none of G-06's three gate claims reach the api
 * surface's route group, and a cookie minted by this app would be cross-site
 * from apps/gallery's own origin. See AssetFavoriteController's own doc
 * comment for the full argument; apps/gallery/src/lib/comments.ts is the
 * client side of the same call.
 *
 * $key must appear in the version's own assets array, same rule as
 * AssetFavoriteController's and GalleryImageController's.
 */
class AssetCommentController extends Controller
{
    public function __construct(private readonly NotifyStudioOfEngagement $notifyStudio) {}

    /** Every comment left on one asset within one version, oldest first. */
    public function index(Request $request, GalleryVersion $galleryVersion): JsonResponse
    {
        $key = (string) $request->query('asset_key', '');

        $known = $key !== '' && collect($galleryVersion->assets)->contains(fn (array $asset) => $asset['key'] === $key);

        $comments = $known
            ? AssetComment::query()
                ->where('gallery_version_id', $galleryVersion->id)
                ->where('asset_key', $key)
                ->orderBy('created_at')
                ->get()
                ->map(fn (AssetComment $comment) => [
                    'body' => $comment->body,
                    'created_at' => $comment->created_at->toIso8601String(),
                ])
            : collect();

        return response()->json(['comments' => $comments->values()]);
    }

    public function store(Request $request, GalleryVersion $galleryVersion): JsonResponse
    {
        $validated = $request->validate([
            'guest_id' => ['required', 'string', 'max:64'],
            'asset_key' => ['required', 'string'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $known = collect($galleryVersion->assets)->contains(fn (array $asset) => $asset['key'] === $validated['asset_key']);
        abort_unless($known, 404);

        $comment = AssetComment::create([
            'gallery_version_id' => $galleryVersion->id,
            'asset_key' => $validated['asset_key'],
            'guest_identifier' => $validated['guest_id'],
            'body' => $validated['body'],
        ]);

        ($this->notifyStudio)(new AssetCommented($galleryVersion->gallery, $comment));

        return response()->json([
            'comment' => [
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
