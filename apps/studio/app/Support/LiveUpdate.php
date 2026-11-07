<?php

namespace App\Support;

use App\Crm\Models\Contact;
use App\Engagement\Models\Thread;
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
        self::post(config('services.mercure.url'), self::topic());
    }

    /**
     * A new line in a thread: tells each client on it to reload their
     * mailbox. The portal has its own hub (it is another host, so the
     * subscriber cookie could not reach the admin's), reached over
     * MERCURE_PORTAL_URL.
     */
    public static function publishThread(Thread $thread): void
    {
        if (config('services.mercure.portal_url') === null) {
            return;
        }

        $contacts = Contact::query()
            ->whereIn('id', $thread->participants()->whereNotNull('contact_id')->select('contact_id'))
            ->pluck('public_id');

        foreach ($contacts as $contactId) {
            self::post(config('services.mercure.portal_url'), self::contactTopic($contactId));
        }
    }

    private static function post(?string $url, string $topic): void
    {
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
            ->withToken(self::token('publish', $key, now()->addMinute()->timestamp, $topic))
            ->asForm()
            ->post($url, ['topic' => $topic, 'data' => '{}'])
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

    /** The signed-in portal contact's cookie, valid for their own topic only. */
    public static function subscribePortal(Contact $contact): ?string
    {
        $key = config('services.mercure.subscriber_key');

        if ($key === null) {
            return null;
        }

        $minutes = (int) config('session.lifetime');
        $topic = self::contactTopic($contact->public_id);

        // The bare name is a local hub on plain HTTP; it must not collide with
        // the admin hub's cookie there (docker-compose.yml, mercure-portal).
        Cookie::queue(cookie(
            config('session.secure') ? '__Secure-mercure_access_token' : 'mercure_portal_access_token',
            self::token('subscribe', $key, now()->addMinutes($minutes)->timestamp, $topic),
            $minutes,
            '/.well-known/mercure',
            sameSite: 'lax',
        ));

        return config('services.mercure.portal_public_url').'?match='.rawurlencode($topic);
    }

    public static function contactTopic(string $contactId): string
    {
        return 'portal/'.config('tenancy.tenant_id').'/'.$contactId;
    }

    public static function topic(): string
    {
        return 'crm/'.config('tenancy.tenant_id');
    }

    /** HS256, the algorithm the hub is configured for. A handful of claims do not need a JWT library. */
    private static function token(string $action, string $key, int $expiresAt, ?string $topic = null): string
    {
        $claims = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'exp' => $expiresAt,
            'authorization_details' => [[
                'type' => 'https://mercure.rocks/authorization-detail',
                'actions' => [$action],
                'topics' => [['match' => $topic ?? self::topic()]],
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
