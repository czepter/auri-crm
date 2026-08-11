<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * F-11's one `X-Request-Id` per request, minted at the edge
 * (App\Http\Middleware\AssignRequestId) when the caller didn't send one, and
 * read back wherever a log line or a Sentry/GlitchTip tag needs it —
 * including a queued job's failure report, once App\Providers\
 * ObservabilityServiceProvider has stamped it onto the job's payload and
 * read it back off on the worker side.
 *
 * A plain static rather than a container binding: this process never runs
 * under Octane (no laravel/octane dependency), so PHP-FPM/artisan serve
 * already start every request with a fresh copy of every static, and
 * App\Providers\ObservabilityServiceProvider explicitly clears it between
 * queue jobs on the worker (a genuinely long-running process) for the same
 * reason.
 */
class RequestId
{
    private static ?string $current = null;

    public static function set(?string $id): void
    {
        self::$current = $id;
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function mint(): string
    {
        return (string) Str::uuid();
    }
}
