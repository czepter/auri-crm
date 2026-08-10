<?php

namespace App\Support\Observability;

use App\Support\RequestId;
use App\Support\Surface;
use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * F-11's structured JSON log channel (config/logging.php's `observability`
 * channel): every line carries the request id, the surface, the tenant, and
 * the actor — by identifier that never leaves the server (see
 * App\Concerns\HasPublicId), not the bigint primary key, the same rule every
 * JSON response in this app already follows.
 *
 * A Monolog processor rather than per-call context: a field only shows up
 * when whoever writes the log line remembers to add it, and "the client
 * says the download failed" needs these four on every line, not on whichever
 * ones a call site thought to attach.
 */
class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra = array_merge($record->extra, array_filter([
            'request_id' => RequestId::current(),
            'surface' => Surface::current(),
            'tenant_id' => config('tenancy.tenant_id'),
            'actor_public_id' => $this->actorPublicId(),
        ], static fn ($value) => $value !== null));

        return $record;
    }

    /**
     * Tries every configured guard rather than the default one: the actor
     * behind a log line can be a staff User (admin guard) or a portal
     * Contact (portal guard), and which one is authenticated depends on
     * which surface produced the line.
     */
    private function actorPublicId(): ?string
    {
        try {
            foreach (array_keys(config('auth.guards')) as $guard) {
                $user = Auth::guard($guard)->user();

                if ($user !== null) {
                    return $user->public_id ?? null;
                }
            }
        } catch (\Throwable) {
            // A log line must never fail to write because resolving "who is
            // logged in" failed — most likely a console/queue context with
            // no request behind it at all.
            return null;
        }

        return null;
    }
}
