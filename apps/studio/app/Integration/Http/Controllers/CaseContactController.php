<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Integration\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A person added to a project after capture — the partner a migration run
 * lost to partner_name (#355). Primary is refused: the one-primary index
 * would throw, and who booked is decided at capture. Role is free text, the
 * same rule LeadContactController gives the admin screen.
 */
class CaseContactController extends Controller
{
    public function store(Request $request, ClientCase $case): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['nullable', 'string', 'size:24', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', 'string', 'max:60'],
        ]);

        $role = $validated['role'] ?? CaseContactRole::Partner->value;

        abort_if($this->isPrimary($role), 422);

        $contact = $this->resolveContact($request, $validated);

        // unique(case_id, contact_id, role) would throw on a second attach.
        if (! $case->contacts()->whereKey($contact->id)->wherePivot('role', $role)->exists()) {
            $case->attachContact($contact, $role);
        }

        return response()->json([
            'id' => $contact->public_id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'role' => $role,
        ], 201);
    }

    /** Same both-spellings guard as LeadContactController::isPrimary. */
    private function isPrimary(string $role): bool
    {
        return $role === CaseContactRole::Primary->value
            || $role === CaseContactRole::Primary->label();
    }

    /**
     * #400: a secondary contact only, same as the primary refusal on store()
     * — the primary is decided at capture, the pivot's partial unique index
     * depends on it staying that way.
     */
    public function destroy(ClientCase $case, Contact $contact): Response
    {
        $role = $case->contacts()->whereKey($contact->id)->first()?->pivot->role;

        abort_if($role === null || $this->isPrimary($role), 422);

        $case->contacts()->detach($contact->id);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveContact(Request $request, array $validated): Contact
    {
        $principal = $request->user('api');
        $can = fn (Capability $c) => $principal instanceof ApiToken
            ? $principal->allows($c)
            : $principal->tokenCan($c->value);

        // Reading an existing contact back through the 201 body is an e-mail
        // oracle, so it needs the same authority as reading contacts.
        $mayUseExisting = $can(Capability::ManageContacts) || $can(Capability::ViewAllCases);

        if (filled($validated['contact_id'] ?? null)) {
            abort_unless($mayUseExisting, 403);

            return Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail();
        }

        // Same rule as CaptureInquiry: an address on file is that person.
        $existing = filled($validated['email'] ?? null)
            ? Contact::query()->where('email', $validated['email'])->first()
            : null;

        // A new contact is the same authority as POST /api/contacts.
        abort_unless($existing !== null ? $mayUseExisting : $can(Capability::ManageContacts), 403);

        return $existing ?? Contact::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);
    }
}
