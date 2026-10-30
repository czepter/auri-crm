<?php

namespace App\Contracts\Http\Controllers;

use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSigner;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Who is expected to sign one Fassung. Only contacts already on the project
 * qualify — a signer is a person the studio has a relationship with, not an
 * address typed into a contract screen.
 *
 * remind() records the nudge and sends nothing: there is no client-facing
 * contract notification in this app (App\Contracts\Notifications\Studio\ContractSigned
 * goes to staff), and inventing one here would put wording in front of a
 * client that nobody wrote. The column is what a mail would read once that
 * notification exists.
 */
class ContractSignerController extends Controller
{
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('manageContracts', $contract->case);

        $validated = $request->validate(['contact' => ['required', 'string']]);

        $contact = $contract->case->contacts()
            ->where('contacts.public_id', $validated['contact'])->firstOrFail();

        $signer = $contract->signers()->firstOrCreate(['contact_id' => $contact->id]);

        AuditLogger::record(
            event: 'contract.signer_added',
            actor: auth('staff')->user(),
            context: ['contact' => $contact->public_id, 'version' => $contract->version],
            subject: $signer->public_id,
            projectId: $contract->case_id,
        );

        return back();
    }

    /** A signature is evidence — the row that carries one is not removable. */
    public function destroy(ContractSigner $signer): RedirectResponse
    {
        $this->authorize('manageContracts', $signer->contract->case);

        abort_unless($signer->signed_at === null, 422);

        $signer->delete();

        return back();
    }

    public function remind(ContractSigner $signer): RedirectResponse
    {
        $this->authorize('manageContracts', $signer->contract->case);

        $signer->update(['reminded_at' => now()]);

        AuditLogger::record(
            event: 'contract.signer_reminded',
            actor: auth('staff')->user(),
            context: ['contact' => $signer->contact->public_id],
            subject: $signer->public_id,
            projectId: $signer->contract->case_id,
        );

        return back();
    }
}
