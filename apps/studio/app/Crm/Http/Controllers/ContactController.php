<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Engagement\Data\MessageData;
use App\Engagement\Models\Message;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Portal\Models\ClientAccess;
use App\Portal\Models\DsgvoRequest;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        return Inertia::render('Contacts/Index', [
            'contacts' => ContactSuggestionData::collect($contacts),
        ]);
    }

    public function edit(Contact $contact, StudioSettingsResolver $settings): Response
    {
        $this->authorize('update', $contact);

        $user = auth('staff')->user();

        return Inertia::render('Contacts/Edit', [
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
            'portalAccessRevokedAt' => $contact->portal_access_revoked_at?->toIso8601String(),
            'canRevokePortalAccess' => $user?->can('revokePortalAccess', $contact) ?? false,
            'dsgvoErasedAt' => $contact->dsgvo_erased_at?->toIso8601String(),
            'canManageDsgvo' => $user?->can('eraseDsgvoData', $contact) ?? false,
            // P-09 (#228): what the portal does for THIS client. Per contact
            // and not per project — a venue with forty dates a year is set up
            // once. Each value is three-valued: absent means inherit, which
            // is why the studio's own value travels beside it.
            'portalDefaults' => [
                'accept_quotes' => $settings->acceptQuotesEnabled(),
                'sign_contracts' => $settings->signContractsEnabled(),
                'payment_status' => $settings->paymentStatusEnabled(),
                'messaging_mode' => $settings->messagingMode(),
                'gallery_downloads' => $settings->galleryDownloadsEnabled(),
                'gallery_lists' => $settings->galleryListsEnabled(),
                'gallery_comments' => $settings->galleryCommentsEnabled(),
                'welcome' => $settings->portalWelcomeText(),
            ],
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

        return redirect('/kontakte');
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
     * hosts.
     */
    public function previewPortal(Contact $contact): RedirectResponse
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

        return redirect()->away(config('portal.url')."/zugang/{$token}");
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

        $validated = $request->validate([
            'accept_quotes' => ['nullable', 'boolean'],
            'sign_contracts' => ['nullable', 'boolean'],
            'payment_status' => ['nullable', 'boolean'],
            'messaging_mode' => ['nullable', 'in:off,booking,any'],
            'gallery_downloads' => ['nullable', 'boolean'],
            'gallery_lists' => ['nullable', 'boolean'],
            'gallery_comments' => ['nullable', 'boolean'],
            'welcome' => ['nullable', 'string', 'max:2000'],
        ]);

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
