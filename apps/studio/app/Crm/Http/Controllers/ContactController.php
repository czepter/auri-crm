<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Engagement\Data\MessageData;
use App\Engagement\Models\Message;
use App\Http\Controllers\Controller;
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

    public function edit(Contact $contact): Response
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
