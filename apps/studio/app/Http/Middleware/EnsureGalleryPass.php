<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryPreviewPass;
use App\Gallery\Support\GalleryViewPass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The api surface's half of G-06.
 *
 * EnsureGalleryGate reads the claim out of the portal surface's session.
 * This surface is a separate stateless process and has none, so #227 shut
 * gated galleries off it entirely — correct, because the alternative was a
 * view password that guarded one screen and left the asset list and the
 * bytes open to anyone holding an id. But it also meant a wedding gallery,
 * the one case a photographer always locks, could not be served by
 * apps/gallery at all.
 *
 * A pass is the same claim in a form that crosses an origin: signed, carrying
 * the gallery and the password version, and worthless the moment the studio
 * rotates. See GalleryViewPass. A gallery with no password needs none.
 *
 * A GalleryPreviewPass (R31) satisfies this gate too, checked first and
 * ahead of both the withdrawal check and the password gate: it proves the
 * studio itself handed out this exact look at the gallery, a stronger claim
 * than merely knowing the password, and withdrawal (G-14) only ever meant
 * "stop showing guests the published version" — a studio reworking a draft
 * behind a withdrawn gallery must still be able to preview it.
 * GalleryReadController still makes the actual draft-vs-version decision —
 * this only decides whether the request reaches it at all.
 *
 * 404 rather than 401: which galleries carry a password is not something
 * this surface owes an anonymous caller either.
 */
class EnsureGalleryPass
{
    public function __construct(
        private readonly GalleryViewPass $pass,
        private readonly GalleryPreviewPass $previewPass,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $gallery = $this->gallery($request);

        if ($gallery === null) {
            return $next($request);
        }

        if ($this->previewPass->opens($gallery, $request->query('preview'))) {
            return $next($request);
        }

        // G-14: closes GalleryReadController, GalleryImageController and
        // the favorites/comments routes alike — the same withdrawal check
        // EnsureGalleryGate applies on the portal surface, for the surface
        // apps/gallery actually calls.
        abort_if($gallery->isWithdrawn(), 404);

        if (! $gallery->requiresViewPassword()) {
            return $next($request);
        }

        abort_unless($this->pass->opens($gallery, $this->token($request)), 404);

        return $next($request);
    }

    /**
     * A bearer where the caller can set headers, a query parameter where it
     * cannot.
     *
     * The second exists for one reason: `<img src>`. Every tile on the guest
     * viewer is an image on this surface, and a browser will not attach an
     * Authorization header to one. The alternatives were worse — proxying
     * every photograph through the Astro process, on the viewer that exists
     * because it is the fast one, or signing fifteen variant URLs per asset
     * and shipping them all down the wire.
     *
     * What it costs is a pass in an access log and a Referer. Bounded, and
     * bounded on purpose: it opens one gallery, for twelve hours, and dies
     * the moment the studio rotates — which is the same set of limits that
     * makes rotation the answer to a link that travelled too far.
     */
    private function token(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->query('pass');
    }

    private function gallery(Request $request): ?Gallery
    {
        $bound = $request->route('gallery') ?? $request->route('galleryVersion');

        return match (true) {
            $bound instanceof Gallery => $bound,
            $bound instanceof GalleryVersion => $bound->gallery,
            default => null,
        };
    }
}
