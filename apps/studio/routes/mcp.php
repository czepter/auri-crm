<?php

use App\Integration\Http\Controllers\OAuthProtectedResourceController;
use App\Integration\Mcp\CrmServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * N-04: the api surface's MCP endpoint, and the one document a client that
 * holds no token yet is allowed to read.
 *
 * At the host root rather than under /api, because a resource identifier is
 * a URL a remote client dials and RFC 9728 puts its metadata beside it —
 * /.well-known/oauth-protected-resource/mcp for a resource at /mcp. Every
 * route the tools themselves call still lives in routes/api.php, behind the
 * /api prefix and its scopes.
 */

// The group, not the returned route's own ->middleware(): Mcp::web also
// registers the GET and DELETE that answer 405, and this surface's rule is
// that every route declares its audience (AudienceMatrixTest).
Route::middleware('audience:api')->group(function () {
    Mcp::web('/mcp', CrmServer::class);
});

/*
 * The same server over stdio, for a client that spawns this app rather than
 * dialing it (`APP_SURFACE=api php artisan mcp:start studio`). There is no
 * request to take a bearer from in that case — see ApiCall.
 */
Mcp::local('studio', CrmServer::class);

Route::get('/.well-known/oauth-protected-resource', OAuthProtectedResourceController::class)
    ->name('mcp.oauth.protected-resource');

// The name laravel/mcp's own AddWwwAuthenticateHeader looks up to point a
// 401 at the document — registered here rather than by Mcp::oauthRoutes(),
// which would describe a Passport authorization server this app does not
// have (its own lives in routes/admin.php).
Route::get('/.well-known/oauth-protected-resource/{path}', OAuthProtectedResourceController::class)
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
