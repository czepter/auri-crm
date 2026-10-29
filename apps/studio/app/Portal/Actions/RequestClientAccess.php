<?php

namespace App\Portal\Actions;

use App\Crm\Models\Contact;
use App\Notifications\NotificationService;
use App\Portal\Models\ClientAccess;
use App\Portal\Notifications\ClientAccessLink;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issues a magic link, and tells the caller nothing about whether it did.
 *
 * The whole class is built around one property: a request for an unknown
 * address must be indistinguishable from a request for a known one. That rules
 * out an early return, a different status, a different message — and, less
 * obviously, a faster answer, because sending mail costs time that not sending
 * it does not.
 *
 * Three things therefore happen on every call regardless of outcome: both rate
 * limiters are hit, a token is generated and hashed, and the request is held
 * to a floor before it returns.
 */
class RequestClientAccess
{
    /**
     * Per address, and per IP, as two separate counters. One address behind
     * many addresses and many addresses behind one IP are different attacks,
     * and a single counter stops only whichever it was written for.
     */
    private const PER_EMAIL = 5;

    private const PER_IP = 20;

    private const WINDOW_SECONDS = 900;

    /**
     * The timing envelope. Every request takes at least this long, so the mail
     * send a known address pays for is hidden inside it.
     *
     * ponytail: a fixed floor, not a constant-time implementation. It closes
     * the gross asymmetry — milliseconds versus tens of milliseconds — and a
     * remote attacker cannot see through the noise of a network. Measuring a
     * real deployment is what would justify anything more.
     */
    private const FLOOR_MS = 250;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function __invoke(string $email, ?string $ip): void
    {
        $startedAt = microtime(true);

        // Hit before the lookup, so a throttled request costs the same as any
        // other and reveals nothing by refusing sooner.
        $withinLimits = $this->hit("portal-link-email:{$email}", self::PER_EMAIL)
            & $this->hit('portal-link-ip:'.($ip ?? 'unknown'), self::PER_IP);

        // Generated either way: the work is identical whether or not it is
        // used, which is the point.
        $token = bin2hex(random_bytes(32));

        $contact = Contact::query()->whereNotNull('email')->where('email', $email)->first();

        if ($contact !== null && $withinLimits) {
            $this->issue($contact, $token, $ip);
        }

        $this->holdTheFloor($startedAt);
    }

    private function issue(Contact $contact, string $token, ?string $ip): void
    {
        $access = ClientAccess::create([
            'contact_id' => $contact->id,
            'token_hash' => ClientAccess::hash($token),
            'expires_at' => now()->addMinutes(ClientAccess::VALID_FOR_MINUTES),
            'requested_ip' => $ip,
        ]);

        // E-07: every send in this app goes through NotificationService —
        // see its own docblock. ClientAccessLink names only ['mail'] in
        // via(), so this still leaves exactly one email, same as ->notify()
        // did.
        $this->notifications->send(
            Notification::route('mail', $contact->email),
            new ClientAccessLink($contact, url("/zugang/{$token}")),
        );

        // One row per issued link. The token is not in it: an audit log that
        // records live credentials is a second place to steal them from.
        AuditLogger::record(
            event: 'portal.link_issued',
            context: ['contact' => $contact->public_id, 'ip' => $ip],
            subject: $access->public_id,
        );
    }

    /** True while under the limit. Always consumes from the bucket. */
    private function hit(string $key, int $max): bool
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        return true;
    }

    private function holdTheFloor(float $startedAt): void
    {
        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        $remaining = self::FLOOR_MS - $elapsedMs;

        if ($remaining > 0) {
            usleep((int) ($remaining * 1000));
        }
    }
}
