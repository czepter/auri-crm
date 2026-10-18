<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * #357: tells every open admin tab that an entry changed. The event carries
 * nothing — the tab reloads its props through its own controller, which is
 * where policies and visibility already live.
 *
 * The hub is Mercure inside the admin container's FrankenPHP
 * (docker/app/mercure.caddy). api, portal, horizon and the scheduler write
 * too, which is why this posts over HTTP: mercure_publish() only reaches a
 * hub in its own process.
 */
class LiveUpdate
{
    /** The `issuer` and `resource_identifier` in docker/app/mercure.caddy. */
    private const ISSUER = 'https://auri-crm.de';

    private const AUDIENCE = 'https://auri-crm.de/.well-known/mercure';

    public static function publish(): void
    {
        $url = config('services.mercure.url');
        $key = config('services.mercure.publisher_key');

        // Unset in the test suite and on a stack without a hub. The image bakes
        // the url, so only the key tells whether this deploy has one.
        if ($url === null || $key === null) {
            return;
        }

        // After commit, or a tab could reload before the row is visible.
        // rescue(): a hub that is down must not fail a write that already
        // happened, but it gets reported.
        DB::afterCommit(fn () => rescue(fn () => Http::connectTimeout(1)
            ->timeout(2)
            ->withToken(self::token('publish', $key, now()->addMinute()->timestamp))
            ->asForm()
            ->post($url, ['topic' => self::topic(), 'data' => '{}'])
            ->throw()));
    }

    /**
     * Queues the cookie the hub authenticates a subscriber by, and returns
     * the URL to subscribe to. Refreshed on every Inertia response, so it
     * lives exactly as long as the session would.
     */
    public static function subscribe(): ?string
    {
        $key = config('services.mercure.subscriber_key');

        if ($key === null) {
            return null;
        }

        $minutes = (int) config('session.lifetime');

        // The hub reads the __Secure- name by default; a local hub on plain
        // HTTP is told the bare one (docker-compose.yml).
        Cookie::queue(cookie(
            config('session.secure') ? '__Secure-mercure_access_token' : 'mercure_access_token',
            self::token('subscribe', $key, now()->addMinutes($minutes)->timestamp),
            $minutes,
            '/.well-known/mercure',
            sameSite: 'lax',
        ));

        return config('services.mercure.public_url').'?match='.rawurlencode(self::topic());
    }

    public static function topic(): string
    {
        return 'crm/'.config('tenancy.tenant_id');
    }

    /** HS256, the algorithm the hub is configured for. A handful of claims do not need a JWT library. */
    private static function token(string $action, string $key, int $expiresAt): string
    {
        $claims = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'exp' => $expiresAt,
            'authorization_details' => [[
                'type' => 'https://mercure.rocks/authorization-detail',
                'actions' => [$action],
                'topics' => [['match' => self::topic()]],
            ]],
        ];

        $segments = array_map(
            fn (array $part) => self::base64Url(json_encode($part, JSON_UNESCAPED_SLASHES)),
            [['alg' => 'HS256', 'typ' => 'at+jwt'], $claims],
        );

        $segments[] = self::base64Url(hash_hmac('sha256', implode('.', $segments), $key, true));

        return implode('.', $segments);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
