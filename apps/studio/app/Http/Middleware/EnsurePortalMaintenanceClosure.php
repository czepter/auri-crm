<?php

namespace App\Http\Middleware;

use App\Models\StudioSettings;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-08's maintenance switch: a temporary closure of the whole portal, with a
 * message — never a third revocation path beside A-04's hide-project and
 * revoke-contact forms. This middleware never reads or writes
 * `case_contacts` or `contacts.portal_access_revoked_at`; it is the one
 * place StudioSettings' portal_maintenance_enabled is read on this
 * surface, and it renders the same closure screen to everyone rather than
 * changing what any one contact is allowed to see.
 *
 * Wraps every portal route — signed-in or not, see routes/portal.php —
 * except the branding logo and the Impressum: a client already looking at
 * the closure screen still needs the studio's legal notice to be reachable
 * from it (a portal without one is abmahnfähig, closed or not).
 *
 * Runs ahead of `guest:portal`/`audience:portal` so an unauthenticated
 * request sees the same closure screen an authenticated one does, rather
 * than a redirect to a login form for a portal that is not taking sign-ins
 * right now.
 */
class EnsurePortalMaintenanceClosure
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) StudioSettings::current()->portal_maintenance_enabled) {
            return $next($request);
        }

        return Inertia::render('Maintenance', [
            'message' => StudioSettings::current()->portal_maintenance_message,
        ])->toResponse($request);
    }
}
