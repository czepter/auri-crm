<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\SignUpForPrerelease;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * R40/R41: the pre-release page's one form. Public, no session, no
 * password — the address it signs up is the only thing this endpoint
 * touches.
 *
 * $gallery arrives as a raw slug-or-public_id, same reasoning as
 * GalleryPassController: a 404 from route-model binding would tell a
 * guesser "that id doesn't exist" before the uniform response below ever
 * runs. That uniform response is the whole point of R41 — a new address, an
 * address already on the list, a gallery not in pre-release and a gallery
 * that does not exist all answer identically, or this endpoint becomes a
 * way to ask "is my guess for this gallery's id right" and "has this couple
 * already signed up".
 */
class GalleryPrereleaseSignupController extends Controller
{
    private const PER_IP = 10;

    private const PER_GALLERY = 30;

    private const DECAY_SECONDS = 300;

    public function __invoke(Request $request, string $gallery, SignUpForPrerelease $signUp): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];

        if (! $this->refused($request, $gallery)) {
            $model = Gallery::findByPublicIdOrSlug($gallery);

            if ($model !== null) {
                $signUp($model, $email);
            }
        }

        return response()->json(['status' => 'received'], 202);
    }

    /**
     * Same per-IP/per-gallery shape GallerySecretGate uses for the password
     * and PIN gates (R41: "rate-limited the way the other public gallery
     * endpoints are"). Not GallerySecretGate itself — that class clears
     * both counters the instant a guess is *correct*, which fits a secret
     * check but not a signup, where every well-formed address "succeeds"
     * and would clear the limiter on every request.
     */
    private function refused(Request $request, string $gallery): bool
    {
        $ipKey = 'gallery-prerelease-ip:'.($request->ip() ?? 'unknown');
        $galleryKey = "gallery-prerelease-gallery:{$gallery}";

        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP) || RateLimiter::tooManyAttempts($galleryKey, self::PER_GALLERY)) {
            return true;
        }

        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($galleryKey, self::DECAY_SECONDS);

        return false;
    }
}
