<?php

namespace App\Http\Middleware;

use App\Crm\Models\Contact;
use App\Support\Surface;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if ($guard->check() && $audience === Surface::PORTAL && $this->accessWasRevoked($guard->id())) {
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

        Auth::shouldUse($audience);

        return $next($request);
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
}
