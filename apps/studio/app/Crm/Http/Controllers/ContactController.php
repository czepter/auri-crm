<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
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
