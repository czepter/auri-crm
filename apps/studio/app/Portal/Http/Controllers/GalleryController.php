<?php

namespace App\Portal\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryListItem;
use App\Gallery\Support\GalleryVisitor;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * P-06 (#225). What the couple may do to their own gallery: rename and
 * delete their selections, put a picture on one, and rotate the gates.
 *
 * These are actions, not a screen. The screen they belong to is the gallery
 * itself (GalleryPasswordController) — one page for both audiences, with the
 * couple's own half below the pictures — because who is looking turned out
 * to be one sentence rather than a routing decision. See GalleryVisitor.
 *
 * On the gates, this screen shows whether one is on and offers the one action
 * that means anything against a link that travelled too far — rotating it. A
 * newly set secret is echoed back once, in the response to the request that
 * set it.
 *
 * C-17 (#253) made the stored values recoverable so the studio can read one
 * out to a guest who asks. That is the admin's screen; what the couple need
 * here is to change a gate, and a value they set themselves is one they
 * already have.
 *
 * Reachable only by a contact on the gallery's own booking. Not a 403: same
 * rule as everywhere else on this surface.
 */
class GalleryController extends Controller
{
    public function __construct(private readonly GalleryVisitor $visitor) {}

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

    private function hasAsset(Gallery $gallery, string $key): bool
    {
        return collect($gallery->currentVersion?->assets ?? [])
            ->contains(fn (array $asset) => $asset['key'] === $key);
    }

    private function ownList(Gallery $gallery, string $list): GalleryList
    {
        $found = $this->visitor->lists($gallery)->firstWhere('public_id', $list);

        abort_if($found === null, 404);

        return $found;
    }

    /**
     * A gallery this visitor is the client of, or a 404. One question, asked
     * the same way everywhere — see GalleryVisitor.
     */
    private function find(string $gallery): Gallery
    {
        $found = Gallery::query()->where('public_id', $gallery)->firstOrFail();

        abort_unless($this->visitor->isClient($found), 404);

        return $found;
    }
}
