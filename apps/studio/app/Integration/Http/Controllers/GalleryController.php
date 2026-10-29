<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Gallery\Actions\PublishGallery;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\GalleryPublished;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use App\Integration\Data\GalleryDetailData;
use App\Integration\Data\GalleryReadData;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request): JsonResponse
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

        $gallery = Gallery::create([
            'title' => $validated['title'],
            'is_external' => $validated['is_external'],
            'external_url' => $validated['external_url'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'downloads_enabled' => $validated['downloads_enabled'] ?? true,
            'lists_enabled' => $validated['lists_enabled'] ?? true,
            'comments_enabled' => $validated['comments_enabled'] ?? true,
            'case_id' => $project?->id,
            'shoot_id' => isset($validated['shoot_id'])
                ? $project?->shoots()->where('public_id', $validated['shoot_id'])->value('id')
                : null,
        ]);

        AuditLogger::record(
            event: 'gallery.created',
            actor: $this->actor($request),
            context: ['title' => $gallery->title],
            subject: $gallery->public_id,
        );

        return $this->answer($gallery, 201);
    }

    /** Expiry and the three feature flags only — view_password, download_pin and design stay admin-only (#405). */
    public function update(Request $request, Gallery $gallery): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'downloads_enabled' => ['sometimes', 'boolean'],
            'lists_enabled' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
        ]);

        $gallery->update($validated);

        LiveUpdate::publish();

        return $this->answer($gallery);
    }

    public function publish(Request $request, Gallery $gallery, PublishGallery $publish, NotificationService $notifications, StudioSettingsResolver $settings): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $version = $publish($gallery);

        AuditLogger::record(
            event: 'gallery.published',
            actor: $this->actor($request),
            context: ['version' => $version?->version],
            subject: $gallery->public_id,
        );

        $contact = $gallery->case?->primaryContact();

        if (filled($validated['subject'] ?? null) && filled($contact?->email)) {
            $notifications->send($contact, new GalleryPublished($gallery, $validated['subject'], $validated['message'] ?? null, $settings));
        }

        return $this->answer($gallery);
    }

    /** Rotates the preview pass with it, same as the admin's own withdraw — see GalleryPublicationController::destroy(). */
    public function withdraw(Request $request, Gallery $gallery): JsonResponse
    {
        $gallery->forceFill(['unpublished_at' => now()])->save();
        $gallery->rotatePreviewPass();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: $this->actor($request),
            context: [],
            subject: $gallery->public_id,
        );

        return $this->answer($gallery);
    }

    public function startPrerelease(Request $request, Gallery $gallery): JsonResponse
    {
        $this->attemptPrereleaseStart($gallery);

        AuditLogger::record(
            event: 'gallery.prerelease_started',
            actor: $this->actor($request),
            context: [],
            subject: $gallery->public_id,
        );

        return $this->answer($gallery);
    }

    /** Deletes every waiting signup with it — none of them was ever notified, see GalleryPrereleaseController::destroy(). */
    public function stopPrerelease(Request $request, Gallery $gallery): JsonResponse
    {
        $gallery->forceFill(['prerelease_at' => null])->save();
        $gallery->prereleaseSignups()->delete();

        AuditLogger::record(
            event: 'gallery.prerelease_stopped',
            actor: $this->actor($request),
            context: [],
            subject: $gallery->public_id,
        );

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

    private function attemptPrereleaseStart(Gallery $gallery): void
    {
        if ($gallery->currentVersion !== null || $gallery->is_external) {
            throw ValidationException::withMessages(['gallery' => __('gallery.prerelease_unavailable')]);
        }

        $gallery->forceFill(['prerelease_at' => now()])->save();
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
