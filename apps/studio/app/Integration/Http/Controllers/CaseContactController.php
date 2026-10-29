<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\CreateContact;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
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
    public function store(Request $request, ClientCase $case, CreateContact $createContact): JsonResponse
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

        $contact = $createContact->pickOrResolve($validated['contact_id'] ?? null, $validated);

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
}
