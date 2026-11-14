<?php

namespace App\Http\Middleware;

use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryViewPass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The image route's own gate. An <img src> cannot send a bearer, so its URL
 * carries a pass, and that pass must open image bytes and nothing else: the
 * view pass stays a header (see EnsureGalleryPass) and is refused in a URL.
 */
class EnsureGalleryImagePass
{
    public function __construct(private readonly GalleryViewPass $pass) {}

    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->route('galleryVersion');
        $gallery = $version instanceof GalleryVersion ? $version->gallery : null;

        if ($gallery === null) {
            return $next($request);
        }

        abort_if($gallery->isWithdrawn(), 404);

        if (! $gallery->requiresViewPassword()) {
            return $next($request);
        }

        $query = $request->query('pass');

        abort_unless(
            $this->pass->opens($gallery, $request->bearerToken())
                || $this->pass->opensImages($gallery, is_string($query) ? $query : null),
            404,
        );

        return $next($request);
    }
}
