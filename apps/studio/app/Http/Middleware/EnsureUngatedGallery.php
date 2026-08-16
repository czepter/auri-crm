<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The api surface's half of G-06. EnsureGalleryGate reads the claim out of
 * the portal surface's session; the api surface is a separate stateless
 * process (Surface::registerRoutes puts it behind the `api` group, no
 * session at all), so it has no way to evaluate that claim and never had.
 *
 * The consequence was that a view password protected the portal screen and
 * nothing else: whoever held the gallery id read the whole asset list, and
 * whoever held a version id read the bytes. This is the honest answer to a
 * gate that cannot be checked here — a gallery carrying a view password is
 * not on the api surface's menu, and the portal serves it instead (P-08).
 *
 * 404, not 403: which galleries carry a password is not something this
 * surface owes an anonymous caller either.
 */
class EnsureUngatedGallery
{
    public function handle(Request $request, Closure $next): Response
    {
        $gallery = $this->gallery($request);

        abort_if($gallery?->requiresViewPassword() === true, 404);

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
