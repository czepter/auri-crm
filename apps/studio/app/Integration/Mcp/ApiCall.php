<?php

namespace App\Integration\Mcp;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tool call is an api call. The MCP server used to be its own Node process
 * and reached routes/api.php over a socket; it now runs inside the same
 * container, and this is what took the socket's place — a request through
 * this app's own HTTP kernel, onto the very route an outside integration
 * would have called.
 *
 * Not a shortcut to the Actions behind those routes, and not an Eloquent
 * query: `audience:api`, `scope:` and `idempotent` are middleware, so only a
 * real request through the router runs them. That is what keeps a tool from
 * reaching further than the token behind it, and what keeps the audit log
 * complete.
 *
 * The bearer travels with it. Over HTTP that is whichever token opened the
 * MCP session (a staff user's own OAuth token, or an ApiToken); over stdio
 * there is no such request, and config('services.mcp.token') is the token
 * the process was started with.
 */
class ApiCall
{
    public function __construct(private readonly Kernel $kernel) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function __invoke(string $method, string $path, array $payload = [], array $headers = []): Response
    {
        $outer = app('request');
        $bearer = $outer instanceof Request ? $outer->bearerToken() : null;
        $bearer ??= config('services.mcp.token');

        // "true"/"false", not http_build_query's 1/0: the api surface
        // validates its boolean filters as `in:true,false`, because Laravel's
        // own boolean rule refuses the literal "false" a query string carries.
        $query = $method === 'GET'
            ? array_map(
                fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value,
                array_filter($payload, fn (mixed $value): bool => $value !== null),
            )
            : [];

        $request = Request::create(
            uri: '/api'.$path.($query === [] ? '' : '?'.http_build_query($query)),
            method: $method,
            server: ['HTTP_ACCEPT' => 'application/json'],
            content: $method === 'GET' ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $request->headers->set('Content-Type', 'application/json');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        if ($bearer !== null) {
            $request->headers->set('Authorization', 'Bearer '.$bearer);
        }

        // A guard caches the request it was resolved with, so the api guard
        // has to be dropped on the way in — otherwise the sub-request is
        // authenticated as whoever opened the MCP session rather than by its
        // own header — and again on the way out, so the outer request gets
        // its own guard back.
        Auth::forgetGuards();

        try {
            return $this->kernel->handle($request);
        } finally {
            app()->instance('request', $outer);
            Facade::clearResolvedInstance('request');
            Auth::forgetGuards();
        }
    }
}
