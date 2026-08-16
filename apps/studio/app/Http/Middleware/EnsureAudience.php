<?php

namespace App\Http\Middleware;

use App\Crm\Models\Contact;
use App\Integration\Models\ApiToken;
use App\Support\AuditLogger;
use App\Support\Surface;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asserts that the request was authenticated by the guard matching the
 * declared audience. Audiences never overlap: a client or guest session must
 * not reach a staff route even if that route's policy would allow it, because
 * policies run too late to be a first line of defence.
 *
 * This is the second of two layers. The first is the surface itself — a staff
 * route does not exist in the portal or api process at all.
 */
class EnsureAudience
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard($audience);

        // A revoked contact's guard may still be "checked in" — the object
        // it cached at login is stale by design, see A-04 — but the very
        // next request from it is turned away here, exactly as a
        // portal_visible flip or a detached contact already takes effect on
        // the next click rather than the next login (HomeController).
        if ($guard->check() && $audience === Surface::PORTAL
            && ($this->accessWasRevoked($guard->id()) || $this->sessionWasEnded($request, $guard->id()))) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if (! $guard->check()) {
            // Each audience has its own way in, and sending a client to the
            // staff login would be both useless and a hint that it exists.
            $entrance = match ($audience) {
                Surface::PORTAL => '/anmelden',
                default => '/login',
            };

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest($entrance);
        }

        // P-09 (#228): a preview session reads and nothing else. A studio
        // owner looking at what a client sees must not be able to accept a
        // quote or sign a contract as them, and one check here beats the
        // same check on every write route this surface will ever grow.
        if ($audience === Surface::PORTAL
            && $request->session()->get('portal_preview') === true
            && ! $request->isMethodSafe()) {
            abort(403, __('portal.preview_is_read_only'));
        }

        Auth::shouldUse($audience);

        // I-01: an ApiToken is the api surface's second kind of credential,
        // and unlike a guest token every call it makes is rate limited and
        // audited — here rather than per route, so neither is something a
        // future route author has to remember to wire up.
        if ($audience === Surface::API && $guard->user() instanceof ApiToken) {
            return $this->handleApiToken($guard->user(), $request, $next);
        }

        return $next($request);
    }

    private function handleApiToken(ApiToken $token, Request $request, Closure $next): Response
    {
        if ($token->isRevoked()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $key = "api-token:{$token->public_id}";

        if (RateLimiter::tooManyAttempts($key, $token->rate_limit_per_minute)) {
            AuditLogger::record(
                event: 'api_token.rate_limited',
                context: ['route' => (string) $request->route()?->getName(), 'method' => $request->method()],
                subject: $token->public_id,
            );

            return response()->json(['message' => 'Too Many Requests.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        AuditLogger::record(
            event: 'api_token.call',
            context: [
                'route' => (string) $request->route()?->getName(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
            ],
            subject: $token->public_id,
        );

        return $response;
    }

    /**
     * Read fresh off the contacts table by id rather than off whatever the
     * guard already resolved and cached: the id is stable across a
     * revocation, the cached object's other columns are not. Scoped to the
     * portal audience only — sessions carry a raw internal id, and a staff
     * user and a contact are separate sequences that can share a value.
     */
    private function accessWasRevoked(int|string|null $contactId): bool
    {
        return $contactId !== null
            && Contact::query()->whereKey($contactId)->whereNotNull('portal_access_revoked_at')->exists();
    }

    /**
     * P-05 (#224)'s "sign out on every device". A session records when it
     * began (MagicLinkController::consume); a client who ends their sessions
     * moves contacts.sessions_valid_from forward, and every session older
     * than that stamp is turned away here on its very next request — the
     * same next-click rule revocation above already follows, and one that
     * needs no cooperation from the session driver.
     *
     * A session with no stamp predates this feature and is treated as older
     * than any stamp, which is the safe direction.
     */
    private function sessionWasEnded(Request $request, int|string|null $contactId): bool
    {
        if ($contactId === null) {
            return false;
        }

        $validFrom = Contact::query()->whereKey($contactId)->value('sessions_valid_from');

        if ($validFrom === null) {
            return false;
        }

        $startedAt = $request->session()->get('portal_session_started_at');

        return $startedAt === null || $startedAt < Carbon::parse($validFrom)->timestamp;
    }
}
