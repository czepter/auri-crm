<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * I-01's per-route half of the scope check: `audience:api` (EnsureAudience)
 * already proves the bearer authenticated and, for an ApiToken specifically,
 * already rate-limits and audits the call — this middleware is the one that
 * knows which Capability a given route requires and refuses a token that
 * was never scoped to it.
 *
 * Two kinds of principal carry a scope: an ApiToken (I-01), checked against
 * its own `scopes` column, or — N-04 — a staff User authenticated by their
 * own OAuth token, checked against that token's Sanctum abilities instead
 * (tokenCan(), not ApiToken::allows(): the grant lives on the token
 * OAuthAuthorizationController minted, not on the user row). A guest token
 * minted on a Contact (G-01) never reaches a route that declares a scope at
 * all, so it falls through to the default and is refused.
 */
class EnsureApiTokenScope
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $required = Capability::tryFrom($capability)
            ?? throw new InvalidArgumentException("Unknown capability scope \"{$capability}\".");

        $principal = $request->user('api');

        $allowed = match (true) {
            $principal instanceof ApiToken => $principal->allows($required),
            $principal instanceof User => $principal->tokenCan($required->value),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
