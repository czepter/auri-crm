<?php

namespace App\Integration\Http\Controllers;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Integration\Support\OAuthClients;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\OAuthAuthorizationCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * N-04: the authorization half of the MCP HTTP transport's OAuth 2.1 flow —
 * an authorization-code-with-PKCE grant against this app's own `staff`
 * guard, not laravel/passport or league/oauth2-server. The only thing this
 * app needed that it did not already have is "a staff user consents, once,
 * to a scoped token," and that is a consent screen plus a Cache-backed code
 * (OAuthAuthorizationCode) in front of Sanctum's own createToken() —
 * OAuthTokenController does the exchange. I-01's ApiToken machinery is
 * untouched: this mints a *different* kind of Sanctum token, on User rather
 * than ApiToken, and scopesFor() is the one place that enforces a token
 * minted here can never do something the consenting user's own StaffRole
 * could not.
 *
 * Every hop back to the client goes through Inertia::location, never
 * redirect()->away(): the consent screen posts through Inertia, and a 302
 * there is followed by fetch() rather than the browser — the callback then
 * reaches claude.ai as a cross-origin XHR and dies in preflight.
 */
class OAuthAuthorizationController extends Controller
{
    public function create(Request $request): Response|HttpResponse
    {
        $clientId = (string) $request->query('client_id');
        $redirectUri = (string) $request->query('redirect_uri');

        if (! OAuthClients::isKnownClient($clientId) || ! OAuthClients::redirectUriAllowed($clientId, $redirectUri)) {
            abort(400, 'Unknown client or redirect_uri.');
        }

        $state = $request->query('state');
        $state = is_string($state) ? $state : null;
        $codeChallenge = $request->query('code_challenge');

        if ($request->query('response_type') !== 'code'
            || $request->query('code_challenge_method') !== 'S256'
            || ! is_string($codeChallenge)
            || $codeChallenge === '') {
            return $this->errorRedirect($redirectUri, 'invalid_request', $state);
        }

        /** @var User $user */
        $user = $request->user('staff');
        $scopes = $this->scopesFor($user, explode(' ', (string) $request->query('scope', '')));

        if ($scopes === []) {
            return $this->errorRedirect($redirectUri, 'invalid_scope', $state);
        }

        return Inertia::render('Auth/OauthAuthorize', [
            'clientId' => $clientId,
            // Whoever registered themselves picked this string, so it is the
            // one thing on the screen this app did not write. Shown because
            // with RFC 7591 open, "which client is asking" is a question only
            // the consent screen can answer.
            'clientName' => OAuthClients::nameFor($clientId) ?? $clientId,
            // The name above is whatever the client called itself, so it
            // cannot be the only thing on screen: registration is open, and
            // a second client may call itself the same. The host the code
            // actually lands on is the part nobody can spoof.
            'redirectHost' => parse_url($redirectUri, PHP_URL_HOST),
            'redirectUri' => $redirectUri,
            'codeChallenge' => $codeChallenge,
            'state' => $state,
            'scope' => implode(' ', $scopes),
            'scopes' => collect($scopes)
                ->map(fn (string $value) => ['value' => $value, 'label' => Capability::from($value)->label()])
                ->all(),
        ]);
    }

    public function store(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'code_challenge' => ['required', 'string'],
            'scope' => ['required', 'string'],
            'state' => ['nullable', 'string'],
            'decision' => ['required', Rule::in(['approve', 'deny'])],
        ]);

        if (! OAuthClients::isKnownClient($validated['client_id'])
            || ! OAuthClients::redirectUriAllowed($validated['client_id'], $validated['redirect_uri'])) {
            abort(400, 'Unknown client or redirect_uri.');
        }

        /** @var User $user */
        $user = $request->user('staff');
        $state = $validated['state'] ?? null;

        if ($validated['decision'] === 'deny') {
            AuditLogger::record('mcp_oauth.denied', $user);

            return $this->errorRedirect($validated['redirect_uri'], 'access_denied', $state);
        }

        $scopes = $this->scopesFor($user, explode(' ', $validated['scope']));

        if ($scopes === []) {
            return $this->errorRedirect($validated['redirect_uri'], 'invalid_scope', $state);
        }

        $code = OAuthAuthorizationCode::issue(
            $user,
            $validated['client_id'],
            $validated['redirect_uri'],
            $validated['code_challenge'],
            $scopes,
        );

        AuditLogger::record('mcp_oauth.authorized', $user, [
            'client_id' => $validated['client_id'],
            'scopes' => $scopes,
        ]);

        return Inertia::location($this->appendQuery($validated['redirect_uri'], ['code' => $code, 'state' => $state]));
    }

    /**
     * Never wider than the consenting user's own role — a requested scope
     * this role does not carry is silently dropped rather than rejected
     * outright, the same "ask for more than you get" tolerance an OAuth
     * client already has to handle for a resource owner who declines part of
     * a request.
     *
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function scopesFor(User $user, array $requested): array
    {
        $requested = array_values(array_filter($requested, fn (string $s) => $s !== ''));
        $roleScopes = array_map(fn (Capability $c) => $c->value, $user->role->capabilities());

        return $requested === [] ? $roleScopes : array_values(array_intersect($requested, $roleScopes));
    }

    private function errorRedirect(string $redirectUri, string $error, ?string $state): HttpResponse
    {
        return Inertia::location($this->appendQuery($redirectUri, ['error' => $error, 'state' => $state]));
    }

    /** @param array<string, ?string> $params */
    private function appendQuery(string $uri, array $params): string
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri.$separator.http_build_query(array_filter($params, fn (?string $v) => $v !== null));
    }
}
