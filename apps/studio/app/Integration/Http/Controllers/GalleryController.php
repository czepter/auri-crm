<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Gallery\Actions\CreateGallery;
use App\Gallery\Actions\PublishGallery;
use App\Gallery\Actions\StartGalleryPrerelease;
use App\Gallery\Actions\StopGalleryPrerelease;
use App\Gallery\Actions\UpdateGallery;
use App\Gallery\Actions\WithdrawGallery;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use App\Integration\Data\GalleryDetailData;
use App\Integration\Data\GalleryReadData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Validation\Rule;

/**
 * #405: the token surface for what GalleryIndexController, GalleryRecord
 * Controller, GalleryPublicationController, GalleryPrereleaseController,
 * GalleryPreviewController and GalleryLightroomExportController already do
 * in the admin — everything short of media, ordering, sets, cover and
 * design, which stay admin-only. Every action reuses the same model calls
 * and Actions those controllers call, so a version bump, a mail or a
 * preview-pass rotation happens exactly once no matter which surface asked
 * for it.
 */
class GalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $since = $request->validate(['since' => ['sometimes', 'date']])['since'] ?? null;

        /** @var CursorPaginator $paginator */
        $paginator = Gallery::query()
            ->when($request->user('api') instanceof User, fn ($query) => $query->where(
                fn ($visible) => $visible->whereDoesntHave('case')
                    ->orWhereHas('case', fn ($case) => $case->visibleTo($request->user('api')))
            ))
            ->with(['case:id,public_id', 'currentVersion'])
            ->withCount(['assets as asset_count' => fn ($query) => $query->inDraft()])
            ->when($since, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->cursorPaginate(25);

        $next = $paginator->nextCursor();

        return response()->json([
            'data' => $paginator->through([GalleryReadData::class, 'fromModel'])->items(),
            'meta' => [
                'next_cursor' => $next?->encode(),
            ],
        ]);
    }

    public function show(Gallery $gallery): JsonResponse
    {
        $gallery->load(['case:id,public_id', 'currentVersion']);

        return response()->json(['data' => GalleryDetailData::fromModel($gallery)]);
    }

    public function store(Request $request, CreateGallery $createGallery): JsonResponse
    {
        $validated = $request->validate([
            'case_id' => ['nullable', 'string', Rule::exists('cases', 'public_id')],
            'shoot_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'is_external' => ['required', 'boolean'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_if:is_external,true'],
            'expires_at' => ['nullable', 'date'],
            'downloads_enabled' => ['nullable', 'boolean'],
            'lists_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
        ]);

        $project = ($validated['case_id'] ?? null) === null
            ? null
            : ClientCase::query()->where('public_id', $validated['case_id'])->firstOrFail();

        abort_if($project && $request->user('api') instanceof User && ! $request->user('api')->can('view', $project), 403);

        $gallery = $createGallery([
            'title' => $validated['title'],
            'is_external' => $validated['is_external'],
            'external_url' => $validated['external_url'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'downloads_enabled' => $validated['downloads_enabled'] ?? true,
            'lists_enabled' => $validated['lists_enabled'] ?? true,
            'comments_enabled' => $validated['comments_enabled'] ?? true,
        ], $project, $validated['shoot_id'] ?? null, $this->actor($request));

        return $this->answer($gallery, 201);
    }

    /** Expiry and the three feature flags only — view_password, download_pin and design stay admin-only (#405). */
    public function update(Request $request, Gallery $gallery, UpdateGallery $updateGallery): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'downloads_enabled' => ['sometimes', 'boolean'],
            'lists_enabled' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
        ]);

        $updateGallery($gallery, $validated);

        return $this->answer($gallery);
    }

    public function publish(Request $request, Gallery $gallery, PublishGallery $publish): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $publish->announce($gallery, $this->actor($request), $validated['subject'] ?? null, $validated['message'] ?? null);

        return $this->answer($gallery);
    }

    /** Rotates the preview pass with it, same as the admin's own withdraw — see GalleryPublicationController::destroy(). */
    public function withdraw(Request $request, Gallery $gallery, WithdrawGallery $withdraw): JsonResponse
    {
        $withdraw($gallery, $this->actor($request));

        return $this->answer($gallery);
    }

    public function startPrerelease(Request $request, Gallery $gallery, StartGalleryPrerelease $start): JsonResponse
    {
        $start($gallery, $this->actor($request));

        return $this->answer($gallery);
    }

    /** Deletes every waiting signup with it — none of them was ever notified, see GalleryPrereleaseController::destroy(). */
    public function stopPrerelease(Request $request, Gallery $gallery, StopGalleryPrerelease $stop): JsonResponse
    {
        $stop($gallery, $this->actor($request));

        return $this->answer($gallery);
    }

    /** Same GalleryPreviewPass as the admin's "Vorschaulink erzeugen" — rotates it, so any earlier link stops working. */
    public function previewLink(Gallery $gallery, GalleryPreviewPass $pass): JsonResponse
    {
        $gallery->rotatePreviewPass();

        return response()->json(['url' => $gallery->publicUrl().'?preview='.$pass->issue($gallery, GalleryPreviewPass::LINK_MINUTES)]);
    }

    /** The filenames a client favorited in the currently published version — see GalleryLightroomExportController. */
    public function favorites(Gallery $gallery): JsonResponse
    {
        $version = $gallery->currentVersion;

        if ($version === null) {
            return response()->json(['data' => []]);
        }

        $filenames = AssetFavorite::query()
            ->where('gallery_version_id', $version->id)
            ->pluck('asset_key')
            ->map(fn (string $key) => basename($key))
            ->unique()
            ->sort()
            ->values();

        return response()->json(['data' => $filenames]);
    }

    private function answer(Gallery $gallery, int $status = 200): JsonResponse
    {
        return response()->json(['data' => GalleryDetailData::fromModel($gallery->fresh(['case:id,public_id', 'currentVersion']))], $status);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one — see QuoteController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
