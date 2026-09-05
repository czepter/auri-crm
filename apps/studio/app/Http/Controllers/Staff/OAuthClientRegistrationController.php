<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Integration\Models\OAuthClient;
use App\Support\OAuthClients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * N-04: RFC 7591 dynamic client registration, open and unauthenticated —
 * which is the point, and is what the spec calls an open registration
 * endpoint. A remote MCP client (claude.ai) is never handed a client_id to
 * paste; it reads registration_endpoint out of the metadata document and
 * asks for one. Refusing that means the connector cannot be added at all.
 *
 * A registration grants nothing on its own: the row is a name and a list of
 * callbacks. Every capability still comes from a staff member reaching the
 * consent screen, signing in, and approving scopes their own role already
 * carries. Public clients only — no client_secret is issued because none is
 * checked; the PKCE verifier is the credential (see OAuthTokenController).
 */
class OAuthClientRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'string', 'max:2048'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'token_endpoint_auth_method' => ['nullable', 'string'],
        ]);

        // The one auth method this server has: a public client proving itself
        // with PKCE. A client insisting on a secret would be told it has one.
        if (($validated['token_endpoint_auth_method'] ?? 'none') !== 'none') {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => 'Only token_endpoint_auth_method "none" is supported.',
            ], 400);
        }

        foreach ($validated['redirect_uris'] as $uri) {
            if (! OAuthClients::isRegisterableRedirectUri($uri)) {
                return response()->json([
                    'error' => 'invalid_redirect_uri',
                    'error_description' => 'redirect_uris must be https, or http on a loopback host.',
                ], 400);
            }
        }

        $client = OAuthClient::create([
            'client_id' => Str::random(32),
            'client_name' => $validated['client_name'] ?? null,
            'redirect_uris' => array_values($validated['redirect_uris']),
        ]);

        return response()->json([
            'client_id' => $client->client_id,
            'client_name' => $client->client_name,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
        ], 201);
    }
}
