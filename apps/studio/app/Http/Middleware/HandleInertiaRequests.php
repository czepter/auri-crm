<?php

namespace App\Http\Middleware;

use App\Crm\Actions\OpenLeads;
use App\Crm\Models\Project;
use App\Data\StaffProfileData;
use App\Help\Support\HelpIndex;
use App\Models\User;
use App\Portal\Support\PortalSettings;
use App\Releases\Support\ReleaseLog;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\LiveUpdate;
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
            // Crew, assignees and bylines name the only person there is.
            'soloStudio' => fn () => $user !== null && User::soloStudio(),
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
            // Die laufende Version und ob release-log eine höhere kennt. Chrome
            // wie die Zähler darüber: die Zeile steht auf jedem Admin-Screen
            // über der Profilzeile. Nur dort — der Portal-Surface hat keine
            // Seitenleiste, in die sie gehörte.
            ...($user !== null && Surface::current() === Surface::ADMIN ? [
                'release' => fn () => [
                    'installed' => app(ReleaseLog::class)->installed(),
                    'updateAvailable' => app(ReleaseLog::class)->updateAvailable(),
                ],
            ] : []),
            // C-03 (#237): what this user last opened. Shared rather than
            // passed per page, because the palette is chrome and opens over
            // whatever screen is up. Lazy for the same reason as the counts.
            'recent' => $user === null ? null : fn () => RecordVisit::recent(),
            // #357: where this tab listens for changes. Eager rather than a
            // closure: it queues the hub's cookie, and a partial reload that
            // does not ask for this prop still has to refresh that.
            'live' => $user !== null && Surface::current() === Surface::ADMIN ? LiveUpdate::subscribe() : null,
            // Welcher Hilfeartikel zu diesem Screen gehört — null bis zwei,
            // Aufgabe zuerst. Aufgelöst aus dem gecachten Index, also ohne
            // Dateizugriff; der Artikeltext kommt erst beim Aufklappen und
            // dann über /hilfe/artikel/{slug}, nicht über Inertia.
            'help' => $user === null ? null : fn () => array_map(
                fn ($entry) => [
                    'slug' => $entry->slug,
                    'titel' => $entry->titel,
                    'art' => $entry->art,
                ],
                app(HelpIndex::class)->forRoute(
                    (string) $request->route()?->getName(),
                    array_map(fn ($c) => $c->value, $user->role->capabilities()),
                ),
            ),
            // T-08: logo, accent colour and the two external legal links —
            // every portal page's own chrome, not one screen's. Lazy and
            // scoped to the portal surface alone, so an admin request never
            // pays for a settings read it has no page for.
            //
            // Paths are hardcoded strings, not route(...) — the same choice
            // every other portal URL in this codebase already makes rather than the named-route
            // helper admin controllers use: the portal and admin surfaces
            // register their route names into one process-wide UrlGenerator
            // during the test suite's surface switching, and this surface's
            // own paths are few and stable enough not to need it.
            ...(Surface::current() === Surface::PORTAL ? [
                'portalBranding' => fn () => [
                    // Falls back the same way `brand` above does. The
                    // wordmark stands on every screen of this surface, and a
                    // studio that has not been through onboarding yet would
                    // otherwise wear a bare dot where its name goes.
                    'displayName' => app(StudioSettingsResolver::class)->displayName() ?? config('app.name'),
                    'logoUrl' => app(StudioSettingsResolver::class)->logoPath() !== null
                        ? '/branding/logo'
                        : null,
                    'accentColor' => app(StudioSettingsResolver::class)->attributes()['accent_color'],
                    'address' => $this->studioAddress(),
                ],
                'portalLegal' => fn () => [
                    'impressumUrl' => '/impressum',
                    'datenschutzUrl' => app(StudioSettingsResolver::class)->legalDatenschutzUrl(),
                    'agbUrl' => app(StudioSettingsResolver::class)->legalAgbUrl(),
                ],
                // The header stands on every screen and its links are the
                // same four everywhere, so what decides them is shared
                // rather than passed per page. Null with nobody signed in:
                // the sign-in screen has no navigation to draw.
                'portalNav' => fn () => $this->portalNav($request),
                // #439: picks the portal's Sie or ihr string set in portal/main.ts.
                'clientRegister' => fn () => app(StudioSettingsResolver::class)->clientRegister(),
            ] : []),
        ];
    }

    /**
     * T-02's postal particulars as the one line a footer wears. Composed
     * from the parts a studio filled in rather than demanding all of them:
     * a studio with a city and no street still has an address to name.
     */
    private function studioAddress(): ?string
    {
        $attributes = app(StudioSettingsResolver::class)->attributes();

        $street = $attributes['street'];
        $city = implode(' ', array_filter([$attributes['postcode'], $attributes['city']]));

        $line = implode(', ', array_filter([$street, $city]));

        return $line === '' ? null : $line;
    }

    /**
     * What the portal header and its mobile twin need to draw themselves:
     * whether the mailbox exists for this contact at all, and how many
     * conversations are waiting on them.
     *
     * The count asks the same question HomeController's own "pending" does —
     * whoever wrote the newest line owes the next one — off latestMessage's
     * single indexed lookup rather than a second definition of "unread".
     *
     * ponytail: two queries per portal request, counted in PHP over every
     * thread this contact has. A client has a handful; make it one aggregate
     * query if anybody ever has hundreds.
     *
     * @return array{messaging: bool, unread: int}|null
     */
    private function portalNav(Request $request): ?array
    {
        $contact = $request->user('portal');

        if ($contact === null) {
            return null;
        }

        $unread = $contact->threads()
            ->with('latestMessage')
            ->get()
            ->filter(fn ($thread) => $thread->latestMessage !== null
                && $thread->latestMessage->contact_id === null)
            ->count();

        return [
            'messaging' => app(PortalSettings::class)->messagingEnabled($contact),
            'unread' => $unread,
            // The home of a client with one booking is that booking, so the
            // nav naming a list would name something they never see.
            'oneBooking' => $contact->visibleProjects()->count() === 1,
        ];
    }
}
