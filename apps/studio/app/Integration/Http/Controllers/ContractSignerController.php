<?php

namespace App\Integration\Http\Controllers;

use App\Contracts\Data\ContractSignerData;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSigner;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The api surface's mirror of App\Contracts\Http\Controllers\
 * ContractSignerController — see that controller's own doc comment on why
 * remind() records the nudge and sends nothing.
 */
class ContractSignerController extends Controller
{
    public function store(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate(['contact' => ['required', 'string']]);

        $contact = $contract->project->contacts()
            ->where('contacts.public_id', $validated['contact'])->firstOrFail();

        $signer = $contract->signers()->firstOrCreate(['contact_id' => $contact->id]);

        AuditLogger::record(
            event: 'contract.signer_added',
            actor: $this->actor($request),
            context: ['contact' => $contact->public_id, 'version' => $contract->version],
            subject: $signer->public_id,
            projectId: $contract->project_id,
        );

        return response()->json(['data' => ContractSignerData::fromModel($signer->fresh('contact'), null)], 201);
    }

    /** A signature is evidence — the row that carries one is not removable. */
    public function destroy(ContractSigner $signer): Response
    {
        abort_unless($signer->signed_at === null, 422);

        $signer->delete();

        return response()->noContent();
    }

    public function remind(Request $request, ContractSigner $signer): JsonResponse
    {
        $signer->update(['reminded_at' => now()]);

        AuditLogger::record(
            event: 'contract.signer_reminded',
            actor: $this->actor($request),
            context: ['contact' => $signer->contact->public_id],
            subject: $signer->public_id,
            projectId: $signer->contract->project_id,
        );

        return response()->json(['data' => ContractSignerData::fromModel($signer->fresh('contact'), null)]);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
