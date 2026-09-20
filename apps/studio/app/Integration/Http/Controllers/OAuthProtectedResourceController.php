<?php

namespace App\Integration\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * RFC 9728: where the MCP endpoint lives and who hands out tokens for it.
 * A client with no token reads this to find out how to get one, so it is
 * served in front of every gate — the twin of the authorization server's own
 * document, which the admin surface serves (OAuthMetadataController).
 *
 * The issuer is the admin surface, not this one: /oauth/authorize,
 * /oauth/token and /oauth/register all live in routes/admin.php.
 */
class OAuthProtectedResourceController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'resource' => config('services.mcp.url'),
            'authorization_servers' => [config('services.admin.url')],
            'bearer_methods_supported' => ['header'],
        ]);
    }
}
