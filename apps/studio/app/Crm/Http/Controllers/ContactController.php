<?php

namespace App\Crm\Http\Controllers;

use App\Catalog\Data\PackageData;
use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Data\ContactRowData;
use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Engagement\Data\MessageData;
use App\Engagement\Models\Message;
use App\Enums\Capability;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryView;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Portal\Models\ClientAccess;
use App\Portal\Models\DsgvoRequest;
use App\Portal\Notifications\PortalAccessInvitation;
use App\Portal\Support\PortalSettings;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use App\Support\RecordVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContactController extends Controller
{
    /** P-09 (#228): what may depart from the studio's default — on the sheet and at creation. */
    private const PORTAL_RULES = [
        'accept_quotes' => ['nullable', 'boolean'],
        'sign_contracts' => ['nullable', 'boolean'],
        'payment_status' => ['nullable', 'boolean'],
        'messaging_mode' => ['nullable', 'in:off,booking,any'],
        'gallery_downloads' => ['nullable', 'boolean'],
        'gallery_lists' => ['nullable', 'boolean'],
        'gallery_comments' => ['nullable', 'boolean'],
        'offers' => ['nullable', 'boolean'],
        'portal_visible' => ['nullable', 'boolean'],
        'welcome' => ['nullable', 'string', 'max:2000'],
    ];

    /** @return array<string, string|bool|null> */
    private function portalDefaults(StudioSettingsResolver $settings, PortalSettings $portal): array
    {
        return [
            'accept_quotes' => $settings->acceptQuotesEnabled(),
            'sign_contracts' => $settings->signContractsEnabled(),
            'payment_status' => $settings->paymentStatusEnabled(),
            'messaging_mode' => $settings->messagingMode(),
            'gallery_downloads' => $settings->galleryDownloadsEnabled(),
            'gallery_lists' => $settings->galleryListsEnabled(),
            'gallery_comments' => $settings->galleryCommentsEnabled(),
            'offers' => $settings->offersEnabled(),
            'welcome' => $settings->portalWelcomeText(),
            'portal_visible' => $portal->projectsVisibleByDefault(),
        ];
    }

    /** @return array{count: int, firstAt: ?string, lastAt: ?string} */
    private function portalLogins(Contact $contact): array
    {
        $consumed = ClientAccess::query()
            ->where('contact_id', $contact->id)
            ->whereNotNull('consumed_at')
            ->whereNull('preview_by_id')
            ->orderBy('consumed_at')
            ->get(['consumed_at'])
            ->pluck('consumed_at');

        return [
            'count' => $consumed->count(),
            'firstAt' => $consumed->first()?->toIso8601String(),
            'lastAt' => $consumed->last()?->toIso8601String(),
        ];
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Contact::class);

        return Inertia::render('Contacts/Index', [
            'contacts' => $this->rows(),
        ]);
    }

    /**
     * C-15 (#251): every contact as a row, for the table and for the pane
     * beside a single contact. The projects come along because every column
     * but the name is read off them — an eager load rather than three counts.
     *
     * @return Collection<int, ContactRowData>
     */
    private function rows(): Collection
    {
        return Contact::query()
            ->with('projects.primaryShoot')
            ->orderBy('name')
            ->get()
            ->map(ContactRowData::fromContact(...));
    }

    public function create(StudioSettingsResolver $settings, PortalSettings $portal): Response
    {
        $this->authorize('update', new Contact);

        return Inertia::render('Contacts/Create', [
            'portalDefaults' => $this->portalDefaults($settings, $portal),
        ]);
    }

    public function edit(Contact $contact, StudioSettingsResolver $settings, PortalSettings $portal): Response
    {
        $this->authorize('update', $contact);

        RecordVisit::record('kontakt', $contact->public_id, $contact->name);

        $user = auth('staff')->user();

        return Inertia::render('Contacts/Edit', [
            // The pane beside the sheet, as on an inquiry: the whole list,
            // narrowed in the browser.
            'contacts' => $this->rows(),
            'contact' => ContactSuggestionData::fromModel($contact->loadCount('projects')),
            // M-06's billing address rides beside ContactSuggestionData
            // rather than inside it: that class is also the typeahead's
            // response shape (ContactSearchController), and a client's home
            // address has no business travelling on every keystroke.
            'billingAddress' => [
                'street' => $contact->street,
                'postcode' => $contact->postcode,
                'city' => $contact->city,
            ],
            // E-11: every thread this contact is on, across projects — the
            // question "what have we actually said to this person" has no
            // other place to be answered, because a thread only ever knows
            // its own project.
            'correspondence' => Message::query()
                ->whereHas('thread.participants', fn ($query) => $query->where('contact_id', $contact->id))
                ->with(['thread.project', 'contact', 'author'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(MessageData::withProject(...))
                ->all(),
            // C-18 (#254): a project is an order with money and dates, a
            // gallery a delivery with an expiry. Mixed into one list they
            // read as the same thing, so they are two sections.
            'projects' => $contact->projects()->with(['primaryShoot', 'latestQuote', 'job'])->get()
                ->map(fn (Project $project) => [
                    'id' => $project->public_id,
                    'name' => $project->name,
                    'role' => $project->pivot->role,
                    'date' => $project->primaryShoot?->date?->format('Y-m-d'),
                    'value' => $project->latestQuote?->total,
                    'booked' => $project->job !== null,
                ])->all(),
            'galleries' => Gallery::query()
                ->whereIn('project_id', $contact->projects()->select('projects.id'))
                ->with('currentVersion')
                ->get()
                ->map(fn (Gallery $gallery) => [
                    'id' => $gallery->public_id,
                    'title' => $gallery->title,
                    'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
                    'assetCount' => count($gallery->currentVersion?->assets ?? []),
                    // C-21 (#256): whether this person has opened it. A
                    // delivered gallery nobody looked at is the one fact the
                    // sheet could never state.
                    'openedOn' => GalleryView::query()
                        ->where('gallery_id', $gallery->id)
                        ->where('contact_id', $contact->id)
                        ->min('viewed_on'),
                ])->all(),
            // Everyone else on this contact's projects — the other half of a
            // couple, the planner, whoever pays. Named with the role they
            // hold, because that is the only thing that makes the list
            // readable.
            'connected' => Contact::query()
                ->whereKeyNot($contact->id)
                ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $contact->projects()->select('projects.id')))
                ->with(['projects' => fn ($query) => $query->whereIn('projects.id', $contact->projects()->select('projects.id'))])
                ->get()
                ->map(fn (Contact $other) => [
                    'id' => $other->public_id,
                    'name' => $other->name,
                    'role' => $other->projects->first()?->pivot->role,
                ])->all(),
            // Was dieses Studio diesem Kunden einzeln vorgeschlagen hat, und
            // woraus es waehlen kann. Beides nur aus dem, was im Portal
            // angeboten wird — ein Vorschlag auf eine zurueckgezogene Leistung
            // waere einer ins Leere.
            'suggestions' => ServiceSuggestion::query()
                ->where('contact_id', $contact->id)
                ->with(['package', 'suggestedBy'])
                ->get()
                ->map(fn (ServiceSuggestion $suggestion) => [
                    'id' => $suggestion->public_id,
                    'package' => $suggestion->package->public_id,
                    'name' => $suggestion->package->name,
                    'note' => $suggestion->note,
                    'author' => $suggestion->suggestedBy?->name,
                    'suggestedOn' => $suggestion->created_at?->format('Y-m-d'),
                    'live' => $suggestion->package->portal_offered,
                ])->all(),
            'offerablePackages' => Package::query()->portalOffered()->orderBy('name')
                ->with('items')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->public_id,
                    'name' => $package->name,
                    'price' => PackageData::fromModel($package)->price,
                    'priceMode' => ($package->portal_price_mode ?? PortalPriceMode::Exact)->value,
                    'requiresDate' => $package->portal_requires_date,
                ])
                ->all(),
            // Wann dieser Kunde im Portal war und wie oft. Aus den
            // eingelösten Links: eine Vorschau des Studios ist kein Besuch
            // des Kunden und zählt deshalb nicht mit.
            'portalLogins' => $this->portalLogins($contact),
            'portalAccessRevokedAt' => $contact->portal_access_revoked_at?->toIso8601String(),
            'canRevokePortalAccess' => $user?->can('revokePortalAccess', $contact) ?? false,
            'canSendPortalAccess' => filled($contact->email) && ($user?->can('sendPortalAccess', $contact) ?? false),
            'dsgvoErasedAt' => $contact->dsgvo_erased_at?->toIso8601String(),
            'canManageDsgvo' => $user?->can('eraseDsgvoData', $contact) ?? false,
            // P-09 (#228): what the portal does for THIS client. Per contact
            // and not per project — a venue with forty dates a year is set up
            // once. Each value is three-valued: absent means inherit, which
            // is why the studio's own value travels beside it.
            'portalDefaults' => $this->portalDefaults($settings, $portal),
            'portalOverrides' => (object) ($contact->portal_overrides ?? []),
            // P-09 (#228): may this person open "Vorschau als Kunde"? The
            // link itself is minted on POST, not rendered into the page — a
            // live credential in a prop is a live credential in a browser
            // history and a screenshot.
            'canPreviewPortal' => $user?->role->allows(Capability::ManageSettings) ?? false,
            // P-05 (#224): what the client asked for from inside the portal.
            // A request nobody at the studio ever sees is worse than no
            // button at all, and the deadline is legal rather than polite.
            'dsgvoRequests' => DsgvoRequest::query()->open()
                ->where('contact_id', $contact->id)
                ->get()
                ->map(fn (DsgvoRequest $request) => [
                    'id' => $request->public_id,
                    'kind' => $request->kind,
                    'requestedAt' => $request->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // ContactPolicy grants the address book one authority, and update()
        // is the write half of it — there is no create ability to name.
        $this->authorize('update', new Contact);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                // An address already on file is the same person
                // (CaptureInquiry::resolveContact), and the partial unique
                // index on (tenant_id, email) would throw where the form
                // should show a field error.
                Rule::unique('contacts', 'email')->where('tenant_id', config('tenancy.tenant_id')),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'portal' => ['array'],
            ...collect(self::PORTAL_RULES)->mapWithKeys(fn ($rules, $key) => ["portal.{$key}" => $rules])->all(),
        ]);

        $overrides = array_filter($validated['portal'] ?? [], fn ($value) => $value !== null && $value !== '');

        $contact = Contact::create(Arr::except($validated, 'portal'));

        // The model's own creating hook writes the studio greeting; these
        // depart from it, so they are merged onto what it left rather than
        // replacing it.
        if ($overrides !== []) {
            $contact->forceFill(['portal_overrides' => array_merge($contact->portal_overrides ?? [], $overrides)])->save();
        }

        AuditLogger::record(
            event: 'contact.created',
            actor: auth('staff')->user(),
            subject: $contact->public_id,
        );

        return redirect("/kontakte/{$contact->public_id}");
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Mirrors the partial unique index on (tenant_id, email).
                // Without this rule the database throws where the form should
                // show a field error.
                Rule::unique('contacts', 'email')
                    ->where('tenant_id', config('tenancy.tenant_id'))
                    ->ignore($contact->id),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
            // M-06: the billing address §14 UStG wants on an invoice. All
            // three nullable — a lead that never becomes a paying client
            // never needs one, and demanding it at capture would slow down
            // the one screen C-04 built to be fast.
            'street' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $contact->update($validated);

        return redirect("/kontakte/{$contact->public_id}");
    }

    /**
     * Cut this one contact's portal access off entirely — every unconsumed
     * magic link and any session already open elsewhere. Never deletes the
     * project_contacts row and never touches any other contact (A-04).
     * Hiding a project from the portal is a separate action on
     * ProjectController, on purpose.
     */
    /**
     * P-09 (#228): "Vorschau als Kunde", on the issue's own terms —
     * expiring, single use and in the audit log, never a silent guard login.
     *
     * A magic link the studio mints for itself rather than a second
     * mechanism beside the one this app already has: ClientAccess brings the
     * expiry, the single use, the hashed token and the revocation check
     * unchanged, and preview_by_id is what makes the session it opens
     * read-only (EnsureAudience) and what the audit row names.
     *
     * The admin process cannot name a route it does not load, so the URL is
     * composed against config('portal.url') — the two surfaces are separate
     * hosts. The button is a form with target="_blank", so this answers a
     * plain navigation and Inertia::location degrades to the 302 that tab
     * follows; the header form stays for anything that does send X-Inertia,
     * which may not follow a redirect across origins.
     */
    public function previewPortal(Contact $contact): HttpResponse
    {
        abort_unless(auth('staff')->user()?->role->allows(Capability::ManageSettings) ?? false, 403);
        abort_if($contact->portal_access_revoked_at !== null, 404);

        $token = bin2hex(random_bytes(32));

        ClientAccess::create([
            'contact_id' => $contact->id,
            'token_hash' => ClientAccess::hash($token),
            'expires_at' => now()->addMinutes(ClientAccess::VALID_FOR_MINUTES),
            'requested_ip' => request()->ip(),
            'preview_by_id' => auth('staff')->id(),
        ]);

        AuditLogger::record(
            event: 'portal.preview_issued',
            actor: auth('staff')->user(),
            context: ['contact' => $contact->public_id],
            subject: $contact->public_id,
        );

        return Inertia::location(config('portal.url')."/zugang/{$token}");
    }

    public function composePortalAccess(Contact $contact, StudioSettingsResolver $settings): Response
    {
        $this->authorize('sendPortalAccess', $contact);
        abort_if($contact->portal_access_revoked_at !== null || blank($contact->email), 404);

        return Inertia::render('Contacts/SendPortalAccess', [
            'contactId' => $contact->public_id,
            'studioName' => $settings->displayName() ?? config('app.name'),
            'recipients' => [['name' => $contact->name, 'email' => $contact->email]],
        ]);
    }

    /** The same ClientAccess row a requested link is, only longer-lived — see INVITATION_VALID_FOR_DAYS. */
    public function sendPortalAccess(Request $request, Contact $contact, StudioSettingsResolver $settings, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('sendPortalAccess', $contact);
        abort_if($contact->portal_access_revoked_at !== null || blank($contact->email), 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $token = bin2hex(random_bytes(32));

        ClientAccess::create([
            'contact_id' => $contact->id,
            'token_hash' => ClientAccess::hash($token),
            'expires_at' => now()->addDays(ClientAccess::INVITATION_VALID_FOR_DAYS),
            'requested_ip' => $request->ip(),
        ]);

        $notifications->send($contact, new PortalAccessInvitation(
            config('portal.url')."/zugang/{$token}",
            $validated['subject'],
            $validated['message'] ?? null,
            $settings,
        ));

        AuditLogger::record(
            event: 'portal.invitation_issued',
            actor: auth('staff')->user(),
            context: ['contact' => $contact->public_id],
            subject: $contact->public_id,
        );

        return redirect("/kontakte/{$contact->public_id}");
    }

    /**
     * P-09 (#228). Writes only the keys that actually depart from the
     * studio's default; anything the form sends as "Standard" is REMOVED
     * rather than stored as a copy of today's value — otherwise a studio
     * changing its own default would silently leave every contact behind on
     * the old one.
     */
    public function updatePortalOverrides(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate(self::PORTAL_RULES);

        $overrides = array_filter($validated, fn ($value) => $value !== null && $value !== '');

        $contact->forceFill(['portal_overrides' => $overrides === [] ? null : $overrides])->save();

        AuditLogger::record(
            event: 'contact.portal_overrides_updated',
            actor: auth('staff')->user(),
            context: ['keys' => array_keys($overrides)],
            subject: $contact->public_id,
        );

        return back();
    }

    public function revokePortalAccess(Contact $contact): RedirectResponse
    {
        $this->authorize('revokePortalAccess', $contact);

        $contact->revokePortalAccess();

        AuditLogger::record(
            event: 'contact.portal_access_revoked',
            actor: auth('staff')->user(),
            subject: $contact->public_id,
        );

        return back();
    }
}
