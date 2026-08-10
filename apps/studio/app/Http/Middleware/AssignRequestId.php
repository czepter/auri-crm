<?php

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-11: mints `X-Request-Id` at the edge when the caller didn't send one,
 * echoes it back on the response, and pushes it into the log context for
 * every log line this request produces. Registered first in the global
 * middleware stack (bootstrap/app.php) rather than in a surface's own `web`/
 * `api` group, so it runs ahead of everything — including whatever else logs
 * during routing or session handling — on all three surfaces.
 *
 * Queue propagation is a separate concern, not this middleware's: see
 * App\Providers\ObservabilityServiceProvider, which stamps
 * RequestId::current() onto every job's payload as it's pushed.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-Id') ?: RequestId::mint();

        RequestId::set($id);
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
