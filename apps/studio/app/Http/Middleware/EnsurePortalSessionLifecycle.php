<?php

namespace App\Http\Middleware;

use App\Crm\Models\Contact;
use App\Support\Surface;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Widerruf, Sitzungsende und Vorschau nur lesend gelten für jede Portal-
 * Anfrage, nicht nur für `audience:portal`: die Galerie-Routen liegen
 * außerhalb davon und lesen trotzdem `auth('portal')`.
 */
class EnsurePortalSessionLifecycle
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Surface::current() !== Surface::PORTAL) {
            return $next($request);
        }

        $guard = Auth::guard(Surface::PORTAL);

        if (! $guard->check()) {
            return $next($request);
        }

        // A revoked contact's guard may still be "checked in" — the object
        // it cached at login is stale by design, see A-04 — but the very
        // next request from it is turned away here, exactly as a
        // portal_visible flip or a detached contact already takes effect on
        // the next click rather than the next login (HomeController).
        if ($this->accessWasRevoked($guard->id()) || $this->sessionWasEnded($request, $guard->id())) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $next($request);
        }

        // P-09 (#228): a preview session reads and nothing else. A studio
        // owner looking at what a client sees must not be able to accept a
        // quote as them, and one check here beats the
        // same check on every write route this surface will ever grow.
        if ($request->session()->get('portal_preview') === true
            && ! $request->isMethodSafe()
            && ! $request->routeIs('portal.logout')) {
            abort(403, __('portal.preview_is_read_only'));
        }

        return $next($request);
    }

    /**
     * Read fresh off the contacts table by id rather than off whatever the
     * guard already resolved and cached: the id is stable across a
     * revocation, the cached object's other columns are not.
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
