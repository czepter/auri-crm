<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Actions\RequestGalleryEmailVerification;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryAccessSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The download gate pair — mockups 11 (PIN) and 12 (email) — behind
 * `gallery.gate:view`, since a guest reaches "download" from inside an
 * already-unlocked gallery, never before it. show() decides which of the two
 * still needs asking the same way GalleryPasswordController::show() decides
 * unlocked-or-not: there is nowhere else to redirect a failing request to but
 * back to itself, so this owns the branching rather than being wrapped by the
 * gates it renders. The actual delivery once both are satisfied is G-10/G-11's
 * — "ready" here is a placeholder, not a download.
 */
class GalleryDownloadController extends Controller
{
    public function show(Gallery $gallery, GalleryAccessSession $claims): Response
    {
        return Inertia::render('Gallery/Download', [
            'gallery' => ['id' => $gallery->public_id, 'title' => $gallery->title],
            'step' => $this->step($gallery, $claims),
        ]);
    }

    public function storePin(Request $request, Gallery $gallery, GalleryAccessSession $claims): RedirectResponse
    {
        if ($gallery->requiresDownloadPin()) {
            $validated = $request->validate(['pin' => ['required', 'string']]);

            if (! $gallery->checkDownloadPin($validated['pin'])) {
                return back()->withErrors(['pin' => __('gallery.pin_wrong')]);
            }
        }

        $claims->grant($gallery, GalleryAccessSession::DOWNLOAD, $gallery->download_pin_version);

        return redirect("/galerien/{$gallery->public_id}/download");
    }

    public function requestEmail(Request $request, Gallery $gallery, RequestGalleryEmailVerification $issue): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $issue($gallery, $validated['email']);

        return back()->with('status', __('gallery.email_link_sent'));
    }

    public function verifyEmail(
        Gallery $gallery,
        string $token,
        ConsumeGalleryEmailVerification $consume,
        GalleryAccessSession $claims,
    ): RedirectResponse {
        $email = $consume($gallery, $token);

        if ($email === null) {
            return redirect("/galerien/{$gallery->public_id}/download")->with('status', __('gallery.email_link_dead'));
        }

        $claims->grant($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version, ['email' => $email]);

        return redirect("/galerien/{$gallery->public_id}/download");
    }

    private function step(Gallery $gallery, GalleryAccessSession $claims): string
    {
        $hasPin = ! $gallery->requiresDownloadPin()
            || $claims->has($gallery, GalleryAccessSession::DOWNLOAD, $gallery->download_pin_version);

        $hasEmail = ! $gallery->requiresVerifiedEmail()
            || $claims->has($gallery, GalleryAccessSession::EMAIL, $gallery->email_verification_version);

        return match (true) {
            ! $hasPin => 'pin',
            ! $hasEmail => 'email',
            default => 'ready',
        };
    }
}
