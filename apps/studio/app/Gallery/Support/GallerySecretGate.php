<?php

namespace App\Gallery\Support;

use Illuminate\Support\Facades\Hash;
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
 * $storedHash null covers both "this gallery has no such secret" and "no
 * gallery with this id exists" — either way Hash::check still runs, against
 * a fixed dummy hash, so a guess against a made-up gallery id costs the same
 * bcrypt work and returns the same answer as a wrong guess against a real
 * one. Callers are expected to treat a null $storedHash's "granted" result
 * as failure regardless (it can never truly match), same as an unknown
 * gallery has no claim to grant.
 */
class GallerySecretGate
{
    private const PER_IP = 10;

    private const PER_GALLERY = 30;

    private const DECAY_SECONDS = 300;

    // An inert bcrypt hash nothing was ever derived from. Hash::check
    // against it always returns false, but still pays a full bcrypt verify
    // — see SessionController::store for the same trick on staff login.
    private const DUMMY_HASH = '$2y$12$uMw29xy3RsSrC1m.HaVdLulVk.HYjAdWq6HMXp1EKLcQJdY2QmGhi';

    public function attempt(?string $ip, string $galleryKey, ?string $storedHash, string $secret): bool
    {
        $ipKey = 'gallery-secret-ip:'.($ip ?? 'unknown');
        $galleryLimitKey = "gallery-secret-gallery:{$galleryKey}";

        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP)
            || RateLimiter::tooManyAttempts($galleryLimitKey, self::PER_GALLERY)) {
            return false;
        }

        $matches = $storedHash !== null && Hash::check($secret, $storedHash);

        if ($storedHash === null) {
            Hash::check($secret, self::DUMMY_HASH);
        }

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
