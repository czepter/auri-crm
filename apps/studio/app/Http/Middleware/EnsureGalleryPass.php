<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryViewPass;
use App\Gallery\Support\GuardsGalleryViewPassword;
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
 * R35/R37: a GalleryPreviewPass (R31) is deliberately NOT checked here, even
 * though GalleryReadController honours one. This gate protects every route
 * in the `gallery.pass` group at once — favorites, comments, version images
 * — and a preview pass proving the studio meant to show one specific draft
 * is not a reason to open all of them. GalleryReadController and
 * GalleryDraftImageController each verify their own GalleryPreviewPass
 * instead, and both sit outside this group entirely (routes/api.php) so
 * neither route, nor any future one, inherits a permission from sitting
 * here that its own controller never actually checked for.
 *
 * 404 rather than 401: which galleries carry a password is not something
 * this surface owes an anonymous caller either.
 */
class EnsureGalleryPass
{
    use GuardsGalleryViewPassword;

    public function __construct(private readonly GalleryViewPass $pass) {}

    public function handle(Request $request, Closure $next): Response
    {
        $gallery = $this->gallery($request);

        if ($gallery === null) {
            return $next($request);
        }

        $this->abortUnlessViewable($gallery, $request, $this->pass);

        return $next($request);
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
