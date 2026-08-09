<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff MFA is mandatory. An authenticated but un-enrolled user is sent to
 * enrollment rather than shown an error, because enrollment is the only
 * action available to them.
 */
class RequireConfirmedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('staff')?->hasConfirmedMfa()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Multi-factor authentication enrollment is required.'], 403)
                : redirect('/auth/mfa/enroll');
        }

        return $next($request);
    }
}
