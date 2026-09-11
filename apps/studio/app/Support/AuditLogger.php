<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Keys dropped outright — never appear in an audit row. */
    private const FORBIDDEN = ['password', 'password_confirmation', 'code', 'secret', 'token'];

    /** Keys kept as evidence of shape but masked. */
    private const REDACTED = ['email'];

    /** Audited on every api call, reads included — no change an open tab could show. */
    private const NOT_A_CHANGE = ['api_token.call', 'api_token.rate_limited'];

    public static function record(
        string $event,
        ?User $actor = null,
        array $context = [],
        ?string $subject = null,
        // ?int rather than ?Project: App\Support is cross-cutting and
        // AuditLog belongs to no module, so importing a Crm model here would
        // hang the shared layer off a domain one.
        ?int $projectId = null,
        // A backdated move keeps created_at as the write time — the trail
        // stays honest about when it was typed — and carries when it happened.
        ?CarbonInterface $occurredAt = null,
    ): AuditLog {
        $log = AuditLog::create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'project_id' => $projectId,
            'subject' => $subject,
            'context' => self::sanitize([
                ...$context,
                ...$occurredAt ? ['occurred_at' => $occurredAt->toIso8601String()] : [],
            ]),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);

        // #357: an audited change is one some open tab may be showing.
        if (! in_array($event, self::NOT_A_CHANGE, true)) {
            LiveUpdate::publish();
        }

        return $log;
    }

    /**
     * Für Schreibwege, die pro Bedienschritt feuern statt pro Entscheidung —
     * der Autosave eines Angebotsentwurfs ist einer davon. Ein Entwurf ist
     * per Definition unfertig; seine Zwischenstände sind keine Historie.
     */
    public static function recordThrottled(
        string $event,
        CarbonInterval $within,
        ?User $actor = null,
        array $context = [],
        ?string $subject = null,
        ?int $projectId = null,
    ): ?AuditLog {
        $recent = AuditLog::query()
            ->where('event', $event)
            ->where('subject', $subject)
            ->where('created_at', '>=', now()->sub($within))
            ->exists();

        return $recent ? null : self::record($event, $actor, $context, $subject, $projectId);
    }

    private static function sanitize(array $context): array
    {
        foreach (self::FORBIDDEN as $key) {
            unset($context[$key]);
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
