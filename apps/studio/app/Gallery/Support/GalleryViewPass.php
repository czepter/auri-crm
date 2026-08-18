<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use Illuminate\Support\Facades\Config;

/**
 * The view claim, in a form that survives crossing an origin.
 *
 * G-06 keeps the claim in the portal surface's session, which works
 * perfectly for the portal and not at all for apps/gallery: that viewer is a
 * separate Astro process on its own host, no cookie of ours reaches it, and
 * the api surface it reads from is stateless by construction. That is why
 * #227 had to close gated galleries off the api surface entirely — and why
 * a wedding gallery, the one case a photographer always puts a password on,
 * could then only be seen on the slower of the two viewers.
 *
 * So the claim travels as a signed string instead of a session: the api
 * surface issues one when a guest gets the password right, and verifies it
 * on every later call. Nothing is stored — the signature is the record —
 * which is what keeps the surface stateless.
 *
 * Three things are inside the signature and each earns its place:
 *
 * The gallery id, so a pass for one gallery opens nothing else.
 *
 * view_password_version, so rotating the password invalidates every pass
 * already handed out. That is the same thing the session claim checks
 * (GalleryAccessSession), and the whole reason rotation is the answer to a
 * link that travelled too far — without it a pass would outlive the secret
 * it was bought with.
 *
 * An expiry, so a pass that leaks out of a browser is worth nothing for
 * long. A guest browsing past it simply answers the gate again.
 */
class GalleryViewPass
{
    /** Long enough to look through a wedding gallery, short enough that a leaked pass is stale. */
    public const VALID_FOR_HOURS = 12;

    public function issue(Gallery $gallery): string
    {
        $payload = implode('.', [
            $gallery->public_id,
            $gallery->view_password_version,
            now()->addHours(self::VALID_FOR_HOURS)->timestamp,
        ]);

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * Does $token open $gallery right now?
     *
     * Every failure answers false without saying which one it was: a guest
     * holding a stale pass and one holding a forged pass get the same gate
     * screen back, same rule GallerySecretGate follows for a wrong password.
     */
    public function opens(Gallery $gallery, ?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 4) {
            return false;
        }

        [$galleryId, $version, $expires, $signature] = $parts;

        // Constant time, so the comparison leaks nothing about the key.
        if (! hash_equals($this->sign("{$galleryId}.{$version}.{$expires}"), $signature)) {
            return false;
        }

        return $galleryId === $gallery->public_id
            && (int) $version === $gallery->view_password_version
            && (int) $expires > now()->timestamp;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('app.key'));
    }
}
