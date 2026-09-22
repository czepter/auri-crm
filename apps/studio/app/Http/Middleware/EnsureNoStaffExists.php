<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first-run gate for /einrichtung: reachable only while the tenant has
 * no staff account at all. The moment one exists — created here or any
 * other way — a GET redirects to /login and a POST 404s forever, same as a
 * route that was never registered. See PUBLIC_ROUTES in AudienceMatrixTest
 * for why that emptiness check stands in for an audience guard on this one
 * route.
 */
class EnsureNoStaffExists
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            abort_unless($request->isMethod('GET'), 404);

            return redirect('/login');
        }

        return $next($request);
    }
}
