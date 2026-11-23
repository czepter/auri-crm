<?php

namespace App\Integration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\OAuthAuthorizationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * N-04's token half: exchanges a single-use authorization code (issued by
 * OAuthAuthorizationController) for a Sanctum personal access token minted
 * on the consenting User — not I-01's ApiToken machine credential, and not a
 * session. The MCP client calls this directly, off-browser, so it carries
 * no staff cookie and no CSRF token (see bootstrap/app.php's
 * validateCsrfTokens exception for this one route). No `audience:` guard
 * either: the code and its PKCE verifier ARE the credential here, the same
 * role a client_secret plays for a confidential client — this app issues
 * none, because a PKCE public client is what an MCP client actually is.
 */
class OAuthTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grant_type' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'code_verifier' => ['required', 'string'],
        ]);

        if ($validated['grant_type'] !== 'authorization_code') {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
        }

        $payload = OAuthAuthorizationCode::consume($validated['code']);

        // The code is bound to the client that asked for it: without this a
        // client could redeem a code issued to another one (RFC 6749 §4.1.3).
        if ($payload === null
            || ! hash_equals($payload['client_id'] ?? '', $validated['client_id'])
            || $payload['redirect_uri'] !== $validated['redirect_uri']
            || ! OAuthAuthorizationCode::verifyPkce($validated['code_verifier'], $payload['code_challenge'])) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        $user = User::find($payload['user_id']);

        if ($user === null) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        $ttl = (int) config('mcp_oauth.token_ttl');
        $token = $user->createToken('mcp', $payload['scopes'], now()->addSeconds($ttl));
        $token->accessToken->forceFill(['oauth_client_id' => $validated['client_id']])->save();

        AuditLogger::record('mcp_oauth.token_issued', $user, [
            'client_id' => $validated['client_id'],
            'scopes' => $payload['scopes'],
        ]);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'scope' => implode(' ', $payload['scopes']),
        ]);
    }
}
