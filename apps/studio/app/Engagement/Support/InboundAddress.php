<?php

namespace App\Engagement\Support;

use App\Engagement\Models\Thread;
use Illuminate\Support\Str;

/**
 * E-12's addressing. Two shapes, one secret behind both.
 *
 * ```
 * <handle>.<secret>@<inbound-domain>       the account catch-all
 * t.<thread-public-id>.<hmac>@<domain>     one thread's reply address
 * ```
 *
 * The HMAC is the tenant's own `inbound_secret` over the local part. That
 * makes an address unguessable, and — the part that matters — makes a `t.`
 * address for a thread the sender was never party to impossible to construct
 * without the secret. Without it, anyone who learned the shape could post
 * into any conversation by guessing a public_id.
 *
 * **Rotation is one write.** Replacing the secret invalidates every address
 * ever issued, and both shapes then fail *closed* — an unverifiable address
 * lands in triage rather than being trusted. The client-visible sender is
 * T-06's from-domain and does not change, so rotating costs the studio no
 * reputation and confuses no client.
 *
 * Truncated to 16 hex characters: 64 bits against an attacker who must also
 * land the delivery through a provider that rate-limits, and a full 64-char
 * digest would make an address no human could read back over the phone.
 */
class InboundAddress
{
    private const HMAC_LENGTH = 16;

    public static function domain(): ?string
    {
        $domain = config('mail.inbound.domain');

        return filled($domain) ? (string) $domain : null;
    }

    /** Everything that follows depends on an inbound domain existing at all. */
    public static function configured(): bool
    {
        return self::domain() !== null;
    }

    /**
     * The catch-all: `studio-name.<secret>@<domain>`. Anything sent here
     * reaches the studio's triage tray — forwarded correspondence, an address
     * the studio published itself, a client writing before a thread exists.
     *
     * The handle is the studio's own name, slugged, because this is the one
     * address a studio prints on a website or reads out over the phone. A
     * generic one would be unguessable and unusable in the same breath.
     *
     * It is not a credential on its own: the `<secret>` half is what makes
     * the address unguessable, and the handle is only there to make it
     * recognisable. Two studios named the same thing collide on the handle
     * and not on the address.
     */
    public static function catchAll(string $studioName, string $secret): ?string
    {
        if (! self::configured()) {
            return null;
        }

        $handle = self::handle($studioName);

        return $handle.'.'.self::sign($handle, $secret).'@'.self::domain();
    }

    /**
     * A studio name as a local-part-safe handle. Falls back rather than
     * returning an empty string: a studio named only in a script this
     * transliteration cannot render would otherwise get `.<secret>@…`, which
     * is a valid-looking address nobody can read.
     */
    public static function handle(string $studioName): string
    {
        $slug = Str::slug($studioName);

        return $slug !== '' ? mb_substr($slug, 0, 40) : 'studio';
    }

    /**
     * The Reply-To on every mail E-11 sends. A client pressing Reply in any
     * mail client threads exactly, with nothing to configure.
     */
    public static function forThread(Thread $thread, string $secret): ?string
    {
        if (! self::configured()) {
            return null;
        }

        $local = 't.'.$thread->public_id;

        return $local.'.'.self::sign($local, $secret).'@'.self::domain();
    }

    /**
     * The thread public_id an address names, but only if its HMAC verifies
     * under this tenant's current secret. Null for anything else — a forged
     * local part, a rotated-away address, or a catch-all.
     */
    public static function threadIdFrom(string $address, string $secret): ?string
    {
        $local = str_contains($address, '@') ? strstr($address, '@', true) : $address;

        // Providers routinely lowercase the local part and some rewrite +tags;
        // the public_id alphabet is lowercase already, so this normalises
        // rather than loses information.
        $parts = explode('.', mb_strtolower((string) $local));

        if (count($parts) !== 3 || $parts[0] !== 't') {
            return null;
        }

        [, $publicId, $mac] = $parts;

        return hash_equals(self::sign("t.{$publicId}", $secret), $mac) ? $publicId : null;
    }

    private static function sign(string $local, string $secret): string
    {
        return substr(hash_hmac('sha256', $local, $secret), 0, self::HMAC_LENGTH);
    }
}
