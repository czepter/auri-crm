<?php

namespace App\Http\Middleware;

use App\Support\Surface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (! Auth::guard($audience)->check()) {
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

        return $next($request);
    }
}
