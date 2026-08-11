<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * N-04's authorization code — deliberately Cache-backed, not a table: a code
 * lives for config('mcp_oauth.code_ttl') seconds and is redeemed exactly
 * once, the same "no ceremony for a value nobody reads twice" call
 * StudioSettingsResolver's own cache entry already makes elsewhere in this
 * app. No public_id, no tenant_id migration to carry, because there is no
 * row — a code that outlives the request that issued it only by minutes has
 * nothing worth persisting once OAuthTokenController consumes it.
 */
final class OAuthAuthorizationCode
{
    private const PREFIX = 'mcp_oauth_code:';

    /** @param list<string> $scopes */
    public static function issue(User $user, string $redirectUri, string $codeChallenge, array $scopes): string
    {
        $code = Str::random(64);

        Cache::put(self::PREFIX.$code, [
            'user_id' => $user->id,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'scopes' => $scopes,
        ], now()->addSeconds((int) config('mcp_oauth.code_ttl')));

        return $code;
    }

    /**
     * Single-use: gone from the cache the moment this is called, whether or
     * not the caller goes on to find the payload valid.
     *
     * @return array{user_id: int, redirect_uri: string, code_challenge: string, scopes: list<string>}|null
     */
    public static function consume(string $code): ?array
    {
        return Cache::pull(self::PREFIX.$code);
    }

    /** RFC 7636 S256: the only method this flow accepts. */
    public static function verifyPkce(string $codeVerifier, string $codeChallenge): bool
    {
        $computed = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return hash_equals($codeChallenge, $computed);
    }
}
