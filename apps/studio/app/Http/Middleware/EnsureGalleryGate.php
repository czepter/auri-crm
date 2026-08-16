<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\GalleryAccessSession;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * G-06's enforcement point: the one place a route this story does not own
 * yet (G-08's grid, G-09's lightbox, G-10/G-11's zip delivery) will declare
 * which of the three gates it needs, the same way `audience:` declares which
 * guard a route needs. Never wraps a gate's own entry screen — that screen
 * decides what to render itself (GalleryPasswordController,
 * GalleryDownloadController) — only routes that require a gate already
 * satisfied before they do anything.
 */
class EnsureGalleryGate
{
    public function __construct(private readonly GalleryAccessSession $claims) {}

    public function handle(Request $request, Closure $next, string $gate): Response
    {
        $gallery = $request->route('gallery');

        // P-08 (#227): the image route is bound on the version, not on the
        // gallery — a version's bytes never change, which is what lets that
        // response claim `immutable` — so the gate it needs is its parent's.
        if ($gallery === null) {
            $version = $request->route('galleryVersion');
            $gallery = $version instanceof GalleryVersion ? $version->gallery : null;
        }

        // G-07: a route whose own controller resolves the gallery by hand
        // (GalleryDownloadController::storePin, for its own uniform-failure
        // reasons — see its docblock) carries the raw public_id here rather
        // than an implicit-bound model, since nothing type-hinted Gallery for
        // binding to run against. Resolve it the same way that controller
        // will — a second lookup on this one path, not worth threading the
        // first one through the request just to save it.
        if ($gallery !== null && ! $gallery instanceof Gallery) {
            $gallery = Gallery::findByPublicId((string) $gallery);
        }

        // A gallery that does not exist has no gate to satisfy or fail —
        // there is nothing to redirect to, and doing so would tell a guesser
        // "no such gallery" before the uniform-failure response below ever
        // gets a chance to. Letting the request through leaves that entirely
        // to the controller, which resolves the same null and answers with
        // the same response a wrong secret on a real gallery gets.
        if ($gallery === null || $this->satisfied($gallery, $gate)) {
            return $next($request);
        }

        return redirect($this->entrance($gallery, $gate));
    }

    private function satisfied(Gallery $gallery, string $gate): bool
    {
        return match ($gate) {
            GalleryAccessSession::VIEW => $this->claims->viewGranted($gallery),
            GalleryAccessSession::DOWNLOAD => ! $gallery->requiresDownloadPin()
                || $this->claims->has($gallery, GalleryAccessSession::DOWNLOAD, $gallery->download_pin_version),
            GalleryAccessSession::EMAIL => ! $gallery->requiresVerifiedEmail()
                || $this->claims->has($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version),
            default => throw new InvalidArgumentException("Unknown gallery gate \"{$gate}\"."),
        };
    }

    private function entrance(Gallery $gallery, string $gate): string
    {
        return match ($gate) {
            GalleryAccessSession::VIEW => "/galerien/{$gallery->public_id}",
            default => "/galerien/{$gallery->public_id}/download",
        };
    }
}
