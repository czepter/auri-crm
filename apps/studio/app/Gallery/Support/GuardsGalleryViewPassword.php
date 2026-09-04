<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use Illuminate\Http\Request;

/**
 * G-06/G-14's password-and-withdrawal check, in one place rather than two.
 *
 * R37: GalleryReadController needs this exact check — a preview pass must
 * open the draft regardless of a view password, which means the route
 * cannot sit behind EnsureGalleryPass's blanket gate (R35: that gate has no
 * vocabulary for "not this one route"). So the controller verifies the
 * password itself, on the same terms the middleware already enforces for
 * every other route in the `gallery.pass` group. One trait, not two
 * hand-kept copies of a security check — see EnsureGalleryPass and
 * GalleryReadController for its two callers.
 */
trait GuardsGalleryViewPassword
{
    private function abortUnlessViewable(Gallery $gallery, Request $request, GalleryViewPass $pass): void
    {
        // G-14: a withdrawn gallery is closed to every guest, view pass or not.
        abort_if($gallery->isWithdrawn(), 404);

        if (! $gallery->requiresViewPassword()) {
            return;
        }

        abort_unless($pass->opens($gallery, $this->viewPassToken($request)), 404);
    }

    /**
     * A bearer where the caller can set headers, a query parameter where it
     * cannot — see EnsureGalleryPass's own doc comment for the full
     * argument (the `<img src>` case that forces the query fallback to
     * exist at all).
     */
    private function viewPassToken(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->query('pass');
    }
}
