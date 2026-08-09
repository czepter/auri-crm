<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
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
        return Inertia::render('Contacts/Edit', [
            'contact' => ContactSuggestionData::fromModel($contact->loadCount('projects')),
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
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
}
