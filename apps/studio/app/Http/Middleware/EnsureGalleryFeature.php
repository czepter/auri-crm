<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whether this gallery offers at all what the route does — the counterpart to
 * EnsureGalleryGate, which asks whether the visitor may. Declared per route
 * in the same `alias:argument` shape, so the two read as one sentence where
 * they stack.
 */
class EnsureGalleryFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $gallery = $request->route('gallery');

        // The viewer's favorite and comment routes are bound on the version,
        // never on the gallery — the switch belongs to its parent.
        if ($gallery === null) {
            $version = $request->route('galleryVersion');
            $gallery = $version instanceof GalleryVersion ? $version->gallery : null;
        }

        if ($gallery !== null && ! $gallery instanceof Gallery) {
            $gallery = Gallery::findByPublicId((string) $gallery);
        }

        // A gallery that does not exist has no offer to withhold, and a 403
        // here would answer "no such gallery" before the controller's own
        // uniform failure gets to — the same reasoning EnsureGalleryGate
        // states for letting a missing gallery through.
        if ($gallery === null) {
            return $next($request);
        }

        // match, not a dynamic attribute: a typo in a route's argument throws
        // here instead of reading null off the model and opening the gate.
        abort_unless(match ($feature) {
            'downloads' => $gallery->downloads_enabled,
            'lists' => $gallery->lists_enabled,
            'comments' => $gallery->comments_enabled,
        }, 403);

        return $next($request);
    }
}
