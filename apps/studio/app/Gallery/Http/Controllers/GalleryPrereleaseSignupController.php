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
 * runs. That uniform response covers exactly what R41 asks it to: a new
 * address, an address already on the list, a gallery not in pre-release
 * and a gallery that does not exist all answer the same 202. It does NOT
 * cover a refused request — see refused()'s own doc comment.
 *
 * R44 (Critical fix): every call this controller ever sees arrives as a
 * server-side fetch from the Astro SSR process (apps/gallery/src/lib/
 * apiClient.ts), never directly from a guest's browser. $request->ip() is
 * therefore one constant value for every guest on every gallery on this
 * installation — a per-IP limiter here does not throttle a person, it
 * throttles the SSR process itself, and the first ten signups of any kind,
 * from anyone, would exhaust it for everyone behind it. Modelled on
 * PublicInquiryController instead (named by routes/api.php's own comment
 * as this endpoint's sibling): a honeypot, a timing floor, and a limit
 * keyed on the address rather than the transport it arrived over. The
 * per-gallery limit survives from the original implementation — that
 * dimension is real here, an IP is not.
 */
class GalleryPrereleaseSignupController extends Controller
{
    private const PER_GALLERY = 30;

    private const GALLERY_DECAY_SECONDS = 300;

    /** One attempt per address, per window — same shape as PublicInquiryController's own byEmail limiter. */
    private const ADDRESS_DECAY_SECONDS = 900;

    /** A human has not read the page and typed an address in under this. */
    private const MIN_ELAPSED_SECONDS = 2;

    public function __invoke(Request $request, string $gallery, SignUpForPrerelease $signUp): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            // Never filled by a human — see the page's own hidden field.
            'website' => ['nullable', 'string', 'max:255'],
            // Seconds since the page rendered, computed server-side by
            // apps/gallery from a hidden timestamp field (this page ships
            // no client JavaScript, so there is no inline script to send
            // it the way PublicInquiryController's snippet does). Optional
            // for the same reason theirs is: a first-ever render has
            // nothing to compute it from.
            'elapsed' => ['nullable', 'integer'],
        ]);

        if ($this->refused($gallery, $validated)) {
            return response()->json(['message' => __('gallery.prerelease_signup_refused')], 429);
        }

        $model = Gallery::findByPublicIdOrSlug($gallery);

        if ($model !== null) {
            $signUp($model, $validated['email']);
        }

        return response()->json(['status' => 'received'], 202);
    }

    /**
     * Unlike PublicInquiryController's own refused(), which answers the
     * same 202 no matter which reason applied, a refusal here is answered
     * honestly (429) — R44's ruling: R41 hides whether a given address is
     * already waiting, not whether this particular request was throttled.
     * "Rate limited" is a fact about request cadence, not about the
     * database, so saying so leaks nothing R41 protects.
     *
     * @param  array{email: string, website: ?string, elapsed: ?int}  $validated
     */
    private function refused(string $gallery, array $validated): bool
    {
        if (filled($validated['website'] ?? null)) {
            return true;
        }

        $elapsed = $validated['elapsed'] ?? null;

        if ($elapsed !== null && $elapsed < self::MIN_ELAPSED_SECONDS) {
            return true;
        }

        $addressKey = 'gallery-prerelease-email:'.sha1(mb_strtolower($validated['email']));
        $galleryKey = "gallery-prerelease-gallery:{$gallery}";

        if (RateLimiter::tooManyAttempts($addressKey, 1) || RateLimiter::tooManyAttempts($galleryKey, self::PER_GALLERY)) {
            return true;
        }

        RateLimiter::hit($addressKey, self::ADDRESS_DECAY_SECONDS);
        RateLimiter::hit($galleryKey, self::GALLERY_DECAY_SECONDS);

        return false;
    }
}
