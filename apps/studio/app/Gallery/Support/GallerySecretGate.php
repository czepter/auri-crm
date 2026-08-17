<?php

namespace App\Gallery\Support;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * G-07's shared check for a guessable gallery secret — the view password
 * (GalleryPasswordController::store) and the download PIN
 * (GalleryDownloadController::storePin). One place for both, so the two
 * stay identical rather than drifting apart the way two hand-copied
 * implementations of a security check tend to.
 *
 * Two independent rate-limit keys, per IP and per gallery: 80 wedding guests
 * legitimately share one venue NAT, so an IP-only limit would lock out the
 * real audience, while a distributed guesser walks straight past a
 * per-gallery-only one. Either tripping blocks the request, checked before
 * the hash comparison — a correct secret submitted while rate-limited still
 * fails until the window clears.
 *
 * $verify null covers both "this gallery has no such secret" and "no gallery
 * with this id exists": the attempt is refused and counted exactly like a
 * wrong guess against a real one, so a guess against a made-up gallery id
 * returns the same answer in the same time.
 *
 * C-17 (#253) took the bcrypt dummy out with the hash. The comparison behind
 * $verify is now hash_equals against a decrypted value, which costs the same
 * whether the gallery exists or not — the timing the dummy existed to level
 * is level without it.
 */
class GallerySecretGate
{
    private const PER_IP = 10;

    private const PER_GALLERY = 30;

    private const DECAY_SECONDS = 300;

    /** @param  ?Closure(string): bool  $verify  the gallery's own check, or null where there is nothing to check against */
    public function attempt(?string $ip, string $galleryKey, ?Closure $verify, string $secret): bool
    {
        $ipKey = 'gallery-secret-ip:'.($ip ?? 'unknown');
        $galleryLimitKey = "gallery-secret-gallery:{$galleryKey}";

        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP)
            || RateLimiter::tooManyAttempts($galleryLimitKey, self::PER_GALLERY)) {
            return false;
        }

        $matches = $verify !== null && $verify($secret);

        if (! $matches) {
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            RateLimiter::hit($galleryLimitKey, self::DECAY_SECONDS);

            return false;
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($galleryLimitKey);

        return true;
    }
}
