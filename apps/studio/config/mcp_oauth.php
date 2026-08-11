<?php

return [

    /*
     * N-04: the MCP HTTP transport's own OAuth 2.1 client. One well-known
     * public client — no client secret, PKCE mandatory — because every MCP
     * client this server talks to today is this same one; a marketplace of
     * third-party MCP clients registering themselves is not a problem this
     * app has yet. See App\Support\OAuthClients.
     */
    'client_id' => env('MCP_OAUTH_CLIENT_ID', 'studio-mcp'),

    /*
     * Exact-match allow list. A redirect_uri whose host is a loopback
     * address (127.0.0.1 / localhost / ::1) is also accepted on any port for
     * the same scheme and path — RFC 8252 §7.3, which OAuth 2.1 folds in for
     * native apps that cannot reserve a fixed port, which is exactly what an
     * MCP client spawning a local callback listener is.
     */
    'redirect_uris' => array_values(array_filter(
        explode(',', (string) env('MCP_OAUTH_REDIRECT_URIS', 'http://127.0.0.1:33418/callback'))
    )),

    // Single-use, minutes-scale by design (RFC 6749 §4.1.2 suggests 10).
    'code_ttl' => (int) env('MCP_OAUTH_CODE_TTL', 300),

    // ponytail: no refresh_token grant — the access token simply outlives an
    // ordinary session and the staff member re-authorizes once it lapses.
    // Add rotation if the monthly re-auth ever becomes the wrong trade-off.
    'token_ttl' => (int) env('MCP_OAUTH_TOKEN_TTL', 60 * 60 * 24 * 30),

];
