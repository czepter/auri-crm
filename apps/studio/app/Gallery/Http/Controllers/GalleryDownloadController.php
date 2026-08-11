<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ConsumeGalleryEmailVerification;
use App\Gallery\Actions\RequestGalleryEmailVerification;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GallerySecretGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * $gallery arrives as the raw public_id rather than an implicit-bound
     * model, same reasoning as GalleryPasswordController::store: a 404 from
     * route-model binding would tell a PIN-guesser "that id doesn't exist"
     * before this method — or EnsureGalleryGate — ever ran. EnsureGalleryGate
     * lets a non-Gallery route value straight through for the same reason;
     * see its own note.
     */
    public function storePin(Request $request, string $gallery, GalleryAccessSession $claims, GallerySecretGate $gate): RedirectResponse
    {
        $model = Gallery::findByPublicId($gallery);
        $requiresPin = $model === null || $model->requiresDownloadPin();

        if ($requiresPin) {
            $validated = $request->validate(['pin' => ['required', 'string']]);

            $granted = $gate->attempt($request->ip(), $gallery, $model?->download_pin_hash, $validated['pin']);

            if (! $granted || $model === null) {
                return back()->withErrors(['pin' => __('gallery.pin_wrong')]);
            }
        }

        $claims->grant($model, GalleryAccessSession::DOWNLOAD, $model->download_pin_version);

        return redirect("/galerien/{$model->public_id}/download");
    }

    /**
     * Rate limited the same two ways as the password/PIN checks (G-07),
     * even though there is no wrong-vs-right secret here to guess — see
     * RequestGalleryEmailVerification's own docblock for why this always
     * sends. Without a per-gallery cap, one gallery link would let anyone
     * spam an unlimited number of inboxes through the studio's own mail
     * sender; without a per-IP cap, one visitor could do the same across
     * every gallery it can reach.
     */
    public function requestEmail(Request $request, Gallery $gallery, RequestGalleryEmailVerification $issue): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $ipKey = 'gallery-email-ip:'.($request->ip() ?? 'unknown');
        $galleryKey = 'gallery-email-gallery:'.$gallery->public_id;

        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($galleryKey, 30)) {
            return back()->with('status', __('gallery.email_link_sent'));
        }

        RateLimiter::hit($ipKey, 300);
        RateLimiter::hit($galleryKey, 300);

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
