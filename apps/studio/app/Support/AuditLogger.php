<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Keys dropped outright — never appear in an audit row. */
    private const FORBIDDEN = ['password', 'password_confirmation', 'code', 'secret', 'token'];

    /** Keys kept as evidence of shape but masked. */
    private const REDACTED = ['email'];

    public static function record(
        string $event,
        ?User $actor = null,
        array $context = [],
        ?string $subject = null,
    ): AuditLog {
        return AuditLog::create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'subject' => $subject,
            'context' => self::sanitize($context),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
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
