<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * N-04: the two discovery documents a remote MCP client reads before it ever
 * shows a consent screen. Both are static descriptions of routes that
 * already existed — the flow did not change, it only became findable.
 *
 * Only RFC 8414, the authorization server half — "where do I send the user,
 * and where do I register." RFC 9728's companion document belongs to the
 * resource, which is the api surface's MCP endpoint, and is served there
 * (App\Integration\Http\Controllers\OAuthProtectedResourceController).
 */
class OAuthMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('oauth.authorize'),
            'token_endpoint' => route('oauth.token'),
            'registration_endpoint' => route('oauth.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => self::scopes(),
        ]);
    }

    /** @return list<string> */
    private static function scopes(): array
    {
        return array_map(fn (Capability $c) => $c->value, Capability::cases());
    }
}
