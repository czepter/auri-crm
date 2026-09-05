<?php

namespace App\Integration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integration\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * N-04: what the MCP server's own HTTP transport calls to check a bearer
 * token before accepting a connection — see packages/mcp/src/httpAuth.ts.
 * Behind the same `api` guard every other bearer on this surface
 * authenticates through, so it answers for either kind: an ApiToken (I-01,
 * a fixed scope list on the token row) or a staff User's OAuth token (N-04,
 * Sanctum's own token abilities). Only "who, and what scopes" — the
 * fine-grained per-route check stays EnsureApiTokenScope's job on whichever
 * route the tool call actually reaches next.
 */
class OAuthTokenInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $principal = $request->user('api');

        return match (true) {
            $principal instanceof ApiToken => response()->json([
                'clientId' => $principal->public_id,
                'scopes' => $principal->scopes ?? [],
                'expiresAt' => null,
            ]),
            $principal instanceof User => response()->json([
                'clientId' => $principal->public_id,
                'scopes' => $principal->currentAccessToken()?->abilities ?? [],
                'expiresAt' => $principal->currentAccessToken()?->expires_at?->getTimestamp(),
            ]),
            default => response()->json(['message' => 'Forbidden.'], 403),
        };
    }
}
