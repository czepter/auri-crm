<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Models\GalleryView;
use App\Http\Controllers\Controller;
use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;
use App\Support\RecordVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * C-19 (#255): the gallery as something one manages, not a data sheet with
 * switches.
 *
 * In practice a gallery is worked on in five separate motions — order the
 * pictures, settle how it looks, regulate the download, set the permissions,
 * look up who took what — so those are five tabs and not five cards stacked
 * on one surface. The head sits above them because link, password, code and
 * the figures hold for every tab; they would otherwise travel from tab to tab
 * or, worse, differ between them.
 *
 * Link, view password and download code stand in clear and are settable here,
 * which is what C-17 (#253) stored them recoverably for. Both are handed to
 * guests anyway; a studio that cannot look one up sets a new one on every
 * enquiry and locks out everybody holding the old.
 *
 * The versions sit with the pictures rather than with the permissions: a
 * version is a published selection, so it is a statement about pictures.
 *
 * balance_settled stays a state derived from the payments and never becomes a
 * switch — it stands as a value beside the block it causes.
 */
class GalleryRecordController extends Controller
{
    public function show(Gallery $gallery): Response
    {
        $this->authorize('manageMedia', $gallery);

        RecordVisit::record('galerie', $gallery->public_id, $gallery->title ?? $gallery->project?->name ?? '');

        $version = $gallery->currentVersion;
        $assets = collect($gallery->assets ?? []);

        return Inertia::render('Galleries/Record', [
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'url' => url("/galerien/{$gallery->public_id}"),
                // C-17 (#253): in clear, because both values are shared with
                // guests anyway and a studio that cannot read one back is
                // forced to rotate it on every enquiry.
                'viewPassword' => $gallery->view_password,
                'downloadPin' => $gallery->download_pin,
                'requiresVerifiedEmail' => $gallery->requiresVerifiedEmail(),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                'balanceSettled' => $gallery->balance_settled,
                'requiresSettledBalance' => $gallery->requires_settled_balance,
                'design' => $gallery->design,
            ],
            // The other galleries of the same booking, as a tab row: they are
            // the ones one flips between.
            'siblings' => $gallery->project_id === null ? [] : Gallery::query()
                ->where('project_id', $gallery->project_id)
                ->get()
                ->map(fn (Gallery $other) => [
                    'id' => $other->public_id,
                    'title' => $other->title,
                    'assetCount' => count($other->assets ?? []),
                    'open' => $other->id === $gallery->id,
                ])->all(),
            'figures' => [
                'published' => $version?->version,
                'assets' => $assets->count(),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                'marked' => $version === null ? 0 : AssetFavorite::query()->where('gallery_version_id', $version->id)->count(),
                'downloaded' => $version === null ? 0 : DownloadEvent::query()
                    ->whereIn('zip_build_id', ZipBuild::query()->where('gallery_version_id', $version->id)->select('id'))
                    ->count(),
                'viewed' => GalleryView::query()->where('gallery_id', $gallery->id)->count(),
            ],
            // Sets are a key on the asset entry (see the migration): an entry
            // without one is in the unnamed set, which is what "Alle Bilder"
            // already means.
            'sets' => $assets->groupBy(fn (array $asset) => $asset['set'] ?? '')
                ->map(fn ($group, $name) => ['name' => $name === '' ? null : $name, 'count' => $group->count()])
                ->values()->all(),
            'assets' => $assets->map(fn (array $asset) => [
                'key' => $asset['key'],
                'set' => $asset['set'] ?? null,
                'src' => $version === null ? null : "/galerie-bilder/{$version->public_id}/{$asset['key']}",
            ])->all(),
            'versions' => $gallery->versions()->orderByDesc('version')->get()
                ->map(fn (GalleryVersion $row) => [
                    'version' => $row->version,
                    'publishedAt' => $row->published_at?->format('Y-m-d'),
                    'assetCount' => count($row->assets ?? []),
                ])->all(),
            'builds' => $version === null ? [] : ZipBuild::query()
                ->where('gallery_version_id', $version->id)
                ->with('parts')
                ->get()
                ->map(fn (ZipBuild $build) => [
                    'id' => $build->public_id,
                    'size' => $build->size->value,
                    'status' => $build->status->value,
                    'parts' => $build->parts->count(),
                    'bytes' => (int) $build->parts->sum('byte_size'),
                ])->all(),
            // Lists carry their owner: gallery_lists.contact_id is set and not
            // nullable, so the sheet shows the person and their selections
            // rather than a heap of nameless picks.
            'lists' => GalleryList::query()
                ->where('gallery_id', $gallery->id)
                ->with(['contact', 'items'])
                ->get()
                ->map(fn (GalleryList $list) => [
                    'id' => $list->public_id,
                    'name' => $list->name,
                    'owner' => $list->contact?->name,
                    'count' => $list->items->count(),
                ])->all(),
            'activity' => $this->activity($gallery, $version),
        ]);
    }

    /**
     * C-20 (#257): three sheets, because the questions differ — who took
     * something, who marked something, who was here at all. One table per
     * sheet, so mail, scope and time stay comparable down the column.
     *
     * Every row states only what is recorded. A download knows its date, not
     * its minute (download_events.request_date is a DATE); a mark knows its
     * guest, not their name unless they signed in.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function activity(Gallery $gallery, ?GalleryVersion $version): array
    {
        return [
            'downloads' => $version === null ? [] : DownloadEvent::query()
                ->whereIn('zip_build_id', ZipBuild::query()->where('gallery_version_id', $version->id)->select('id'))
                ->with('zipBuild.galleryList')
                ->latest('request_date')
                ->limit(50)
                ->get()
                ->map(fn (DownloadEvent $event) => [
                    'who' => $event->requester_identifier,
                    'what' => $event->zipBuild?->galleryList?->name,
                    'size' => $event->zipBuild?->size->value,
                    'on' => $event->request_date?->format('Y-m-d'),
                ])->all(),
            'marks' => $version === null ? [] : AssetFavorite::query()
                ->where('gallery_version_id', $version->id)
                ->with('contact')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (AssetFavorite $favorite) => [
                    'who' => $favorite->contact?->name,
                    'what' => $favorite->asset_key,
                    'on' => $favorite->created_at->format('Y-m-d'),
                ])->all(),
            'comments' => $version === null ? [] : AssetComment::query()
                ->where('gallery_version_id', $version->id)
                ->with('contact')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (AssetComment $comment) => [
                    'who' => $comment->contact?->name,
                    'what' => $comment->body,
                    'on' => $comment->created_at->format('Y-m-d'),
                ])->all(),
            'views' => GalleryView::query()
                ->where('gallery_id', $gallery->id)
                ->with('contact')
                ->latest('viewed_on')
                ->limit(50)
                ->get()
                ->map(fn (GalleryView $view) => [
                    'who' => $view->contact?->name,
                    'on' => $view->viewed_on->format('Y-m-d'),
                ])->all(),
        ];
    }

    /** The two secrets and the look, set where they are read. */
    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'view_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'download_pin' => ['nullable', 'string', 'min:4', 'max:64'],
            'design' => ['nullable', 'array'],
            'design.cover' => ['nullable', 'string', 'max:32'],
            'design.type' => ['nullable', 'string', 'max:32'],
            'design.colour' => ['nullable', 'string', 'max:32'],
            'design.grid' => ['nullable', 'string', 'max:32'],
        ]);

        // Through rotate*(), never a plain write: the version bump is what
        // turns out every guest already inside, and it must not be
        // forgettable.
        if ($request->has('view_password')) {
            $gallery->rotateViewPassword($validated['view_password'] ?? null);
        }

        if ($request->has('download_pin')) {
            $gallery->rotateDownloadPin($validated['download_pin'] ?? null);
        }

        if ($request->has('design')) {
            $gallery->update(['design' => $validated['design'] ?? null]);
        }

        return back();
    }
}
