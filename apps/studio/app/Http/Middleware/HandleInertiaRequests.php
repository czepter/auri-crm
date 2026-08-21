<?php

namespace App\Http\Middleware;

use App\Crm\Actions\OpenLeads;
use App\Crm\Models\Project;
use App\Data\StaffProfileData;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\RecordVisit;
use App\Support\Surface;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Each surface has its own root view, so each surface loads its own Vite
     * entrypoint and its own theme. There is no shared HTML shell.
     */
    public function rootView(Request $request): string
    {
        return 'app-'.Surface::current();
    }

    public function share(Request $request): array
    {
        $user = $request->user('staff');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : StaffProfileData::fromModel($user),
                // What this person may do, so a screen can leave out what they
                // cannot reach rather than render it and hide it. A control
                // that exists in the markup is a control someone can find.
                'can' => $user === null
                    ? []
                    : array_map(fn ($capability) => $capability->value, $user->role->capabilities()),
            ],
            // The studio's own name. Shared on every surface rather than only
            // in the portal's branding block: the admin wordmark and the
            // sign-in screens wear it with nobody signed in yet. Falls back
            // until onboarding names the studio.
            'brand' => fn () => app(StudioSettingsResolver::class)->displayName() ?? config('app.name'),
            // P-09 (#228): whether this is a studio member looking at what a
            // client sees. Every portal screen wears the banner, so it is
            // shared rather than passed per page.
            'portalPreview' => $request->session()->get('portal_preview') === true,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                // P-06 (#225): a freshly set gallery secret, readable exactly
                // once. It is hashed the moment it is saved, so this flash is
                // not a convenience — it is the whole window in which anyone,
                // client or studio, can read the value they just chose.
                'secret' => fn () => $request->session()->get('secret'),
            ],
            // The sidebar's counts. Lazy closures, so they cost nothing on the
            // login page and nothing on a partial reload that does not ask for
            // them.
            //
            // ponytail: two counts per authenticated request, unbounded by
            // date. Fine at a studio's volume; if the board ever pages, these
            // become one grouped query instead.
            'nav' => $user === null ? null : [
                'leads' => fn () => app(OpenLeads::class)->count(),
                // T-11's bell. Lazy like its neighbours, so a partial reload
                // that does not ask for it pays nothing.
                'unread' => fn () => $user->inAppNotifications()->whereNull('read_at')->count(),
                // The same two filters /projekte itself applies: booked only,
                // and only what this role may see. A badge that counts rows
                // the list refuses to show is worse than no badge.
                'projects' => fn () => Project::query()->visibleTo($user)->whereHas('job')->count(),
            ],
            // C-03 (#237): what this user last opened. Shared rather than
            // passed per page, because the palette is chrome and opens over
            // whatever screen is up. Lazy for the same reason as the counts.
            'recent' => $user === null ? null : fn () => RecordVisit::recent(),
            // T-08: logo, accent colour and the two external legal links —
            // every portal page's own chrome, not one screen's. Lazy and
            // scoped to the portal surface alone, so an admin request never
            // pays for a settings read it has no page for.
            //
            // Paths are hardcoded strings, not route(...) — the same choice
            // every other portal URL in this codebase already makes (see
            // ContractController's downloadUrl) rather than the named-route
            // helper admin controllers use: the portal and admin surfaces
            // register their route names into one process-wide UrlGenerator
            // during the test suite's surface switching, and this surface's
            // own paths are few and stable enough not to need it.
            ...(Surface::current() === Surface::PORTAL ? [
                'portalBranding' => fn () => [
                    'displayName' => app(StudioSettingsResolver::class)->displayName(),
                    'logoUrl' => app(StudioSettingsResolver::class)->logoPath() !== null
                        ? '/branding/logo'
                        : null,
                    'accentColor' => app(StudioSettingsResolver::class)->attributes()['accent_color'],
                ],
                'portalLegal' => fn () => [
                    'impressumUrl' => '/impressum',
                    'datenschutzUrl' => app(StudioSettingsResolver::class)->legalDatenschutzUrl(),
                    'agbUrl' => app(StudioSettingsResolver::class)->legalAgbUrl(),
                ],
            ] : []),
        ];
    }
}
