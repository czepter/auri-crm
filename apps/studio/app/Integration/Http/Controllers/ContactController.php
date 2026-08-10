<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * I-03: "create contact" on the api surface. No existing Action encodes bare
 * contact creation to reuse — App\Crm\Actions\CaptureInquiry's own
 * resolveContact() only ever creates one as a side effect of capturing a
 * lead — so this mirrors App\Crm\Http\Controllers\ContactController::
 * update()'s own validation instead, the closest existing rule set this
 * model has.
 */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                // Mirrors the partial unique index on (tenant_id, email).
                Rule::unique('contacts', 'email')->where('tenant_id', config('tenancy.tenant_id')),
            ],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);

        $contact = Contact::create($validated);

        return response()->json([
            'id' => $contact->public_id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
        ], 201);
    }
}
