<?php

namespace App\Http\Middleware;

use App\Integration\Models\ApiToken;
use App\Support\AuditLogger;
use App\Support\Surface;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asserts that the request was authenticated by the guard matching the
 * declared audience. Audiences never overlap: a client or guest session must
 * not reach a staff route even if that route's policy would allow it, because
 * policies run too late to be a first line of defence.
 *
 * This is the second of two layers. The first is the surface itself — a staff
 * route does not exist in the portal or api process at all.
 */
class EnsureAudience
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard($audience);

        if (! $guard->check()) {
            // Each audience has its own way in, and sending a client to the
            // staff login would be both useless and a hint that it exists.
            $entrance = match ($audience) {
                Surface::PORTAL => '/anmelden',
                default => '/login',
            };

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest($entrance);
        }

        Auth::shouldUse($audience);

        // I-01: an ApiToken is the api surface's second kind of credential,
        // and unlike a guest token every call it makes is rate limited and
        // audited — here rather than per route, so neither is something a
        // future route author has to remember to wire up.
        if ($audience === Surface::API && $guard->user() instanceof ApiToken) {
            return $this->handleApiToken($guard->user(), $request, $next);
        }

        return $next($request);
    }

    private function handleApiToken(ApiToken $token, Request $request, Closure $next): Response
    {
        if ($token->isRevoked()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $key = "api-token:{$token->public_id}";

        if (RateLimiter::tooManyAttempts($key, $token->rate_limit_per_minute)) {
            AuditLogger::record(
                event: 'api_token.rate_limited',
                context: ['route' => (string) $request->route()?->getName(), 'method' => $request->method()],
                subject: $token->public_id,
            );

            return response()->json(['message' => 'Too Many Requests.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        AuditLogger::record(
            event: 'api_token.call',
            context: [
                'route' => (string) $request->route()?->getName(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
            ],
            subject: $token->public_id,
        );

        return $response;
    }
}
