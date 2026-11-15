<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\MergeContacts;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMergeController extends Controller
{
    public function show(Contact $contact, Contact $other): JsonResponse
    {
        $this->authorize('update', $contact);
        $this->authorize('update', $other);

        return response()->json([
            'name' => $other->name,
            'email' => $other->email,
            'phone' => $other->phone,
            'street' => $other->street,
            'postcode' => $other->postcode,
            'city' => $other->city,
        ]);
    }

    public function store(Request $request, Contact $contact, MergeContacts $merge): RedirectResponse
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'duplicate' => ['required', 'string', 'size:24', Rule::notIn([$contact->public_id]), Rule::exists('contacts', 'public_id')],
            'choices' => ['array'],
            'choices.*' => ['in:survivor,duplicate'],
        ]);

        $duplicate = Contact::query()->where('public_id', $validated['duplicate'])->firstOrFail();

        $this->authorize('update', $duplicate);

        abort_if($contact->isDsgvoErased() || $duplicate->isDsgvoErased(), 422);

        $merge($contact, $duplicate, $validated['choices'] ?? [], auth('staff')->user());

        return redirect("/kontakte/{$contact->public_id}");
    }
}
