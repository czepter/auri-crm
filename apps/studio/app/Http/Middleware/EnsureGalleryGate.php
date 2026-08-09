<?php

namespace App\Http\Middleware;

use App\Gallery\Models\Gallery;
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
        /** @var Gallery $gallery */
        $gallery = $request->route('gallery');

        if ($this->satisfied($gallery, $gate)) {
            return $next($request);
        }

        return redirect($this->entrance($gallery, $gate));
    }

    private function satisfied(Gallery $gallery, string $gate): bool
    {
        return match ($gate) {
            GalleryAccessSession::VIEW => ! $gallery->requiresViewPassword()
                || $this->claims->has($gallery, GalleryAccessSession::VIEW, $gallery->view_password_version),
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
