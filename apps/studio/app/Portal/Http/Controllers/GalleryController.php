<?php

namespace App\Portal\Http\Controllers;

use App\Crm\Models\Contact;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryListItem;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * P-06 (#225). The gallery from the couple's side — not the pictures
 * themselves (that is P-08's grid), but what is theirs about it: the link
 * they hand out, the gates in front of it, and their own selections.
 *
 * On the gates, one rule runs through everything here: **no value is ever
 * shown**. view_password_hash and download_pin_hash carry Laravel's
 * 'hashed' cast (App\Gallery\Models\Gallery), so from the moment a secret is
 * saved it is gone for everyone, the studio included. What this screen shows
 * is whether a gate is on, and the one action that means anything against a
 * link that travelled too far — rotating it. A newly set secret is readable
 * exactly once, in the response to the request that set it.
 *
 * Reachable only by a contact on the gallery's own booking. Not a 403: same
 * rule as everywhere else on this surface.
 */
class GalleryController extends Controller
{
    public function show(string $gallery): Response
    {
        $found = $this->find($gallery);
        $contact = auth('portal')->user();
        $version = $found->currentVersion;

        return Inertia::render('Gallery/Overview', [
            'contact' => ['name' => $contact->name],
            'gallery' => [
                'id' => $found->public_id,
                'title' => $found->title,
                'count' => $version === null ? 0 : count($version->assets),
                'expiresAt' => $found->expires_at?->format('Y-m-d'),
                'url' => url("/galerien/{$found->public_id}"),
                'cover' => $this->assetUrl($found, 0),
            ],
            'gates' => [
                'viewPassword' => $found->requiresViewPassword(),
                'downloadPin' => $found->requiresDownloadPin(),
                'emailVerification' => $found->requiresVerifiedEmail(),
            ],
            'lists' => $this->lists($found, $contact),
            // "Eure Gäste": what the people the link was shared with did.
            // Views are new (DownloadEvent only ever counted downloads);
            // marks and comments are the guests', not the client's own.
            'guests' => [
                'views' => (int) $found->view_count,
                'marks' => $version === null ? 0 : AssetFavorite::query()
                    ->where('gallery_version_id', $version->id)->count(),
                'comments' => $version === null ? 0 : AssetComment::query()
                    ->where('gallery_version_id', $version->id)->count(),
            ],
        ]);
    }

    public function storeList(Request $request, string $gallery): RedirectResponse
    {
        $found = $this->find($gallery);

        GalleryList::create([
            'gallery_id' => $found->id,
            'contact_id' => auth('portal')->id(),
            'name' => $request->validate(['name' => ['required', 'string', 'max:60']])['name'],
        ]);

        return back();
    }

    public function renameList(Request $request, string $gallery, string $list): RedirectResponse
    {
        $this->ownList($this->find($gallery), $list)
            ->update($request->validate(['name' => ['required', 'string', 'max:60']]));

        return back();
    }

    /** The unnamed list is the heart; deleting it would be deleting a control, not a list. */
    public function destroyList(string $gallery, string $list): RedirectResponse
    {
        $found = $this->ownList($this->find($gallery), $list);

        abort_if($found->name === null, 404);

        $found->delete();

        return back();
    }

    /**
     * Add or remove one picture. The unnamed list is addressed by the string
     * "herz" rather than by an id, so the heart works before the row behind
     * it exists.
     */
    public function toggleItem(Request $request, string $gallery, string $list): RedirectResponse
    {
        $found = $this->find($gallery);
        $key = $request->validate(['key' => ['required', 'string']])['key'];

        abort_unless($this->hasAsset($found, $key), 404);

        $target = $list === 'herz'
            ? GalleryList::unnamedFor($found, auth('portal')->user())
            : $this->ownList($found, $list);

        $existing = $target->items()->where('asset_key', $key)->first();

        if ($existing !== null) {
            $existing->delete();

            return back();
        }

        GalleryListItem::create([
            'gallery_list_id' => $target->id,
            'asset_key' => $key,
            'position' => (int) $target->items()->max('position') + 1,
        ]);

        return back();
    }

    /**
     * Rotating a gate is the only real answer to a link that was shared too
     * widely, and the version bump is what makes it one: every guest already
     * inside is invalidated along with the secret (GalleryAccessSession).
     * The three rotate*() methods existed on the model and had no caller
     * outside the admin.
     *
     * An empty value clears the gate. The new value is echoed back once, in
     * the flash bag, and never read from the database again — it cannot be.
     */
    public function rotateGate(Request $request, string $gallery, string $gate): RedirectResponse
    {
        $found = $this->find($gallery);

        $secret = $request->validate(['secret' => ['nullable', 'string', 'min:4', 'max:64']])['secret'] ?? null;

        match ($gate) {
            'ansicht' => $found->rotateViewPassword($secret),
            'download' => $found->rotateDownloadPin($secret),
            'email' => $found->rotateEmailVerification($secret !== null),
            default => abort(404),
        };

        AuditLogger::record('portal.gallery.gate_rotated', null, ['gate' => $gate], $found->public_id);

        return back()->with('secret', $secret);
    }

    /** @return array<int, array<string, mixed>> */
    private function lists(Gallery $gallery, Contact $contact): array
    {
        $lists = GalleryList::query()
            ->where('gallery_id', $gallery->id)
            ->where('contact_id', $contact->id)
            ->with('items')
            ->get()
            // The heart first, then the named ones in the order they were made.
            ->sortBy(fn (GalleryList $list) => [$list->name === null ? 0 : 1, $list->id])
            ->values();

        $version = $gallery->currentVersion;
        $keys = $version === null ? [] : collect($version->assets)->pluck('key')->all();

        return $lists->map(fn (GalleryList $list) => [
            'id' => $list->public_id,
            'name' => $list->name,
            // Keys the current version no longer has are left out rather
            // than rendered as broken pictures — a re-publish that dropped
            // one shortens the list, it does not break it.
            'keys' => $list->items->pluck('asset_key')->intersect($keys)->values()->all(),
            'covers' => $list->items->pluck('asset_key')->intersect($keys)->take(8)
                ->map(fn (string $key) => "/galerie-bilder/{$version->public_id}/{$key}")
                ->values()->all(),
            'changedAt' => $list->updated_at->toIso8601String(),
        ])->all();
    }

    private function hasAsset(Gallery $gallery, string $key): bool
    {
        return collect($gallery->currentVersion?->assets ?? [])
            ->contains(fn (array $asset) => $asset['key'] === $key);
    }

    private function assetUrl(Gallery $gallery, int $index): ?string
    {
        $version = $gallery->currentVersion;
        $key = $version?->assets[$index]['key'] ?? null;

        return $key === null ? null : "/galerie-bilder/{$version->public_id}/{$key}";
    }

    private function ownList(Gallery $gallery, string $list): GalleryList
    {
        return GalleryList::query()
            ->where('gallery_id', $gallery->id)
            ->where('contact_id', auth('portal')->id())
            ->where('public_id', $list)
            ->firstOrFail();
    }

    /**
     * A gallery on one of this contact's own visible bookings, or a 404.
     * DB::table rather than a relation hop: galleries hang off projects, and
     * this is the same visibleProjects() scope every other portal controller
     * asks, phrased once.
     */
    private function find(string $gallery): Gallery
    {
        $visible = auth('portal')->user()->visibleProjects()->pluck('projects.id');

        return Gallery::query()
            ->where('public_id', $gallery)
            ->whereIn('project_id', $visible)
            ->firstOrFail();
    }
}
