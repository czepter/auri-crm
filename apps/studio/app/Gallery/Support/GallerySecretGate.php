<?php

namespace App\Gallery\Support;

use Closure;
use Illuminate\Support\Facades\Crypt;
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
 * C-17 (#253) replaced the hash with an encrypted value, so the work the real
 * path does is one decrypt plus one hash_equals. The null path does the same
 * against a dummy ciphertext rather than nothing at all: without it, "no such
 * gallery" would return measurably faster than "wrong password", which is the
 * enumeration G-07 closes wearing a stopwatch.
 */
class GallerySecretGate
{
    private const PER_IP = 10;

    private const PER_GALLERY = 30;

    private const DECAY_SECONDS = 300;

    /**
     * @param  ?string  $ip  null drops the per-IP key: the api surface only ever sees the gallery SSR container's address (R44)
     * @param  ?Closure(string): bool  $verify  the gallery's own check, or null where there is nothing to check against
     */
    public function attempt(?string $ip, string $galleryKey, ?Closure $verify, string $secret): bool
    {
        $ipKey = $ip !== null ? "gallery-secret-ip:{$ip}" : null;
        $galleryLimitKey = "gallery-secret-gallery:{$galleryKey}";

        if (($ipKey !== null && RateLimiter::tooManyAttempts($ipKey, self::PER_IP))
            || RateLimiter::tooManyAttempts($galleryLimitKey, self::PER_GALLERY)) {
            return false;
        }

        $matches = $verify !== null ? $verify($secret) : $this->dummy($secret);

        if (! $matches) {
            if ($ipKey !== null) {
                RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            }
            RateLimiter::hit($galleryLimitKey, self::DECAY_SECONDS);

            return false;
        }

        if ($ipKey !== null) {
            RateLimiter::clear($ipKey);
        }
        RateLimiter::clear($galleryLimitKey);

        return true;
    }

    /**
     * The same work a real check does — decrypt, then compare — against a
     * value nothing was derived from. Always false, and always the same cost
     * as a wrong guess against a gallery that exists.
     */
    private function dummy(string $secret): bool
    {
        return hash_equals(Crypt::decryptString(Crypt::encryptString('gallery-secret-gate')), $secret);
    }
}
