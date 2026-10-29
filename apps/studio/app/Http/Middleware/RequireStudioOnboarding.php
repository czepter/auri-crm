<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Models\StudioSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends whoever can finish the studio's first-run configuration to
 * /einrichtung/studio until they do. GET-only: the wizard's own steps save
 * through the ordinary settings endpoints this same route group holds
 * (settings.update, settings.vat.update, ...), and those must stay
 * reachable or the wizard could never save itself. Scoped to
 * Capability::ManageSettings so an invited role that cannot open the
 * wizard either (AuthorizesStaffSettings::authorizeSettings() 404s them on
 * every settings screen) is never caught behind this redirect.
 */
class RequireStudioOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($request->isMethod('GET')
            && ($user?->can(Capability::ManageSettings->value) ?? false)
            && ! StudioSettings::current()->onboardingCompleted()
        ) {
            return redirect('/einrichtung/studio');
        }

        return $next($request);
    }
}
