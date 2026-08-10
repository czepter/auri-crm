<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * I-01's per-route half of the scope check: `audience:api` (EnsureAudience)
 * already proves the bearer authenticated and, for an ApiToken specifically,
 * already rate-limits and audits the call — this middleware is the one that
 * knows which Capability a given route requires and refuses a token that
 * was never scoped to it. Only ApiToken callers carry a scope at all; a
 * guest token minted on a Contact (G-01) never reaches a route that
 * declares one.
 */
class EnsureApiTokenScope
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $required = Capability::tryFrom($capability)
            ?? throw new InvalidArgumentException("Unknown capability scope \"{$capability}\".");

        $principal = $request->user('api');

        if (! $principal instanceof ApiToken || ! $principal->allows($required)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
