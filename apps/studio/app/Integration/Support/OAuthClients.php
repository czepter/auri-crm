<?php

namespace App\Integration\Support;

use App\Integration\Models\OAuthClient;

/**
 * N-04's client allow list. Two kinds of client, one lookup: the well-known
 * one from config/mcp_oauth.php (a local MCP client, shipped knowing its own
 * id) and any row registered over RFC 7591 (a remote one, claude.ai being
 * the reason that path exists at all).
 *
 * Every question here is asked *per client*. A global "is this redirect_uri
 * allowed anywhere" answer was fine while there was one client; with a
 * registration endpoint it would let a client start a flow that lands its
 * code on another client's callback, which is the whole attack RFC 6749
 * §10.6 names.
 */
final class OAuthClients
{
    public static function isKnownClient(string $clientId): bool
    {
        return self::redirectUrisFor($clientId) !== null;
    }

    public static function redirectUriAllowed(string $clientId, string $redirectUri): bool
    {
        $allowed = self::redirectUrisFor($clientId);

        if ($allowed === null || $redirectUri === '') {
            return false;
        }

        return in_array($redirectUri, $allowed, true) || self::isLoopbackMatch($redirectUri, $allowed);
    }

    public static function nameFor(string $clientId): ?string
    {
        if (self::isWellKnown($clientId)) {
            return null;
        }

        return OAuthClient::query()->where('client_id', $clientId)->value('client_name');
    }

    /**
     * What a client may register. HTTPS, or the http loopback RFC 8252 §7.3
     * carves out for a native app that cannot reserve a port — an MCP client
     * spawning a local callback listener being exactly that. A fragment is
     * never legal on a redirect_uri (RFC 6749 §3.1.2).
     */
    public static function isRegisterableRedirectUri(string $redirectUri): bool
    {
        $parts = parse_url($redirectUri);

        if ($parts === false || isset($parts['fragment']) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        return $parts['scheme'] === 'https'
            || ($parts['scheme'] === 'http' && self::isLoopbackHost($parts['host']));
    }

    /** @return list<string>|null Null means "no such client", never "no URIs". */
    private static function redirectUrisFor(string $clientId): ?array
    {
        if ($clientId === '') {
            return null;
        }

        if (self::isWellKnown($clientId)) {
            /** @var list<string> */
            return config('mcp_oauth.redirect_uris');
        }

        /** @var list<string>|null */
        return OAuthClient::query()->where('client_id', $clientId)->value('redirect_uris');
    }

    private static function isWellKnown(string $clientId): bool
    {
        return hash_equals((string) config('mcp_oauth.client_id'), $clientId);
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
