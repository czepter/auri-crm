<?php

namespace App\Http\Middleware;

use App\Automation\Models\AutomationRun;
use App\Crm\Actions\OpenLeads;
use App\Crm\Models\Stage;
use App\Crm\Screens\OpenCases;
use App\Data\StaffProfileData;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\MergeFieldVocabulary;
use App\Enums\Capability;
use App\Help\Support\HelpIndex;
use App\Models\StudioSettings;
use App\Models\User;
use App\Portal\Support\LegalTexts;
use App\Portal\Support\PortalSettings;
use App\Releases\Support\ReleaseLog;
use App\Support\LiveUpdate;
use App\Support\RecordVisit;
use App\Support\Surface;
use App\Templates\Http\Controllers\TemplateHubController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            // Where the settings menu points outside the settings: the stage
            // overlay and the template hub, each behind its own gate.
            'settingsLinks' => fn () => $user === null ? null : [
                'stages' => Gate::forUser($user)->allows('viewAny', Stage::class),
                'templates' => app(TemplateHubController::class)->allowedKinds() !== [],
            ],
            // #490: der Löschtag, solange das Studio zum Löschen vorgemerkt ist.
            'studioDeletion' => fn () => $user === null ? null : StudioSettings::current()->deletionDate()?->format('d.m.Y'),
            // The studio's own name. Shared on every surface rather than only
            // in the portal's branding block: the admin wordmark and the
            // sign-in screens wear it with nobody signed in yet. Falls back
            // until onboarding names the studio.
            'brand' => fn () => StudioSettings::current()->display_name ?? config('app.name'),
            // P-09 (#228): whether this is a studio member looking at what a
            // client sees. Every portal screen wears the banner, so it is
            // shared rather than passed per page.
            'portalPreview' => $request->session()->get('portal_preview') === true,
            // Crew, assignees and bylines name the only person there is.
            'soloStudio' => fn () => $user !== null && User::soloStudio(),
            // #480: what the letter fields of quotes and invoices offer —
            // DocumentBlockCatalog's text blocks and every merge field.
            'letterEditor' => fn () => $user === null ? null : [
                'palette' => DocumentBlockCatalog::letter(),
                'mergeFields' => collect(MergeFieldVocabulary::samples())
                    ->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])
                    ->values()
                    ->all(),
            ],
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
                // The projects the rail and the list's "Laufend" show: booked,
                // visible to this role, not delivered, completed or closed.
                'projects' => fn () => app(OpenCases::class)->count($user),
                // Client mails an automation drafted; the Posteingang row's badge.
                'drafts' => fn () => $user->can(Capability::ManageMessages->value)
                    ? (AutomationRun::awaitingFor($user)->count() ?: null)
                    : null,
            ],
            // Die laufende Version und ob release-log eine höhere kennt. Chrome
            // wie die Zähler darüber: die Zeile steht auf jedem Admin-Screen
            // über der Profilzeile. Nur dort — der Portal-Surface hat keine
            // Seitenleiste, in die sie gehörte.
            ...($user !== null && Surface::current() === Surface::ADMIN ? [
                'release' => fn () => app(ReleaseLog::class)->base() === null ? null : [
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
                    'displayName' => StudioSettings::current()->display_name ?? config('app.name'),
                    'logoUrl' => StudioSettings::current()->logo_path !== null
                        ? '/branding/logo'
                        : null,
                    'accentColor' => StudioSettings::current()->profile()['accent_color'],
                    'address' => StudioSettings::current()->postalLine(),
                ],
                // Je Text die fremde Adresse, die eigene Seite oder nichts —
                // siehe LegalTexts::url().
                'portalLegal' => fn () => collect(LegalTexts::ALL)
                    ->mapWithKeys(fn (string $text) => [
                        $text.'Url' => LegalTexts::url($text),
                    ])
                    ->all(),
                // AGB und Widerruf, die dem Angebot beiliegen: nur diese
                // stehen neben „Angebot annehmen“.
                'portalQuoteLegal' => fn () => LegalTexts::attachedUrls(),
                // The header stands on every screen and its links are the
                // same four everywhere, so what decides them is shared
                // rather than passed per page. Null with nobody signed in:
                // the sign-in screen has no navigation to draw.
                'portalNav' => fn () => $this->portalNav($request),
                // #439: picks the portal's Sie or ihr string set in portal/main.ts.
                'clientRegister' => fn () => StudioSettings::current()->client_register,
            ] : []),
        ];
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
     * @return array{messaging: bool, unread: int, oneBooking: bool, booked: bool}|null
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
            'booked' => $contact->visibleProjects()->whereNotNull('cases.booked_at')->exists(),
        ];
    }
}
