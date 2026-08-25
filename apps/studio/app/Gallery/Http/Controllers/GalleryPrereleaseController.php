<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * R32/R39: the More menu's "Pre-release" — shares a gallery's address
 * before it has anything published. Authorised against `publish`
 * (ManagePortalAccess), not `manageMedia`: this makes the gallery's title
 * reachable by anyone holding the link, the same judgment GalleryPolicy
 * already makes for who may decide a gallery is presentable outside the
 * studio (see the policy's own doc comment on that ability).
 */
class GalleryPrereleaseController extends Controller
{
    public function store(Gallery $gallery): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        // Both guarded here, not just hidden from the More menu: a gallery
        // already published has nothing left for R39's "no version yet" to
        // mean, and an external gallery owns no draft to be "prepared" —
        // Gallery::publish() itself refuses one for the same reason.
        if ($gallery->currentVersion !== null || $gallery->is_external) {
            throw ValidationException::withMessages(['gallery' => __('gallery.prerelease_unavailable')]);
        }

        $gallery->forceFill(['prerelease_at' => now()])->save();

        AuditLogger::record(
            event: 'gallery.prerelease_started',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $gallery->forceFill(['prerelease_at' => null])->save();

        AuditLogger::record(
            event: 'gallery.prerelease_stopped',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }
}
