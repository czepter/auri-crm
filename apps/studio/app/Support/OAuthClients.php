<?php

namespace App\Support;

/**
 * N-04's client allow list — see config/mcp_oauth.php for why this is one
 * well-known client rather than a registration table.
 */
final class OAuthClients
{
    public static function isKnownClient(string $clientId): bool
    {
        return $clientId !== '' && hash_equals((string) config('mcp_oauth.client_id'), $clientId);
    }

    public static function redirectUriAllowed(string $redirectUri): bool
    {
        if ($redirectUri === '') {
            return false;
        }

        /** @var list<string> $allowed */
        $allowed = config('mcp_oauth.redirect_uris');

        return in_array($redirectUri, $allowed, true) || self::isLoopbackMatch($redirectUri, $allowed);
    }

    /** @param list<string> $allowed */
    private static function isLoopbackMatch(string $redirectUri, array $allowed): bool
    {
        $candidate = parse_url($redirectUri);

        if (($candidate['scheme'] ?? null) !== 'http' || ! self::isLoopbackHost($candidate['host'] ?? '')) {
            return false;
        }

        foreach ($allowed as $uri) {
            $configured = parse_url($uri);

            if (($configured['scheme'] ?? null) === 'http'
                && self::isLoopbackHost($configured['host'] ?? '')
                && ($configured['path'] ?? '') === ($candidate['path'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
