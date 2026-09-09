<?php

namespace App\Http\Middleware;

use App\Support\SilentWrite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A header rather than a body field: it applies to every write endpoint at
 * once, including the ones whose bodies are a closed schema (C-25's field
 * values), and it never reaches an Action as data it would have to carry
 * down. Registered on the api surface only — the board and the portal have
 * a human watching what they trigger.
 */
class MarkSilentWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (filter_var($request->header('X-Silent-Write', ''), FILTER_VALIDATE_BOOL)) {
            SilentWrite::mark();
        }

        return $next($request);
    }
}
