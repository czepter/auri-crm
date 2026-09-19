<?php

namespace App\Contracts\Actions;

use App\Contracts\Enums\ContractStatus;
use App\Contracts\Events\ContractSigned;
use App\Contracts\Models\Contract;
use App\Contracts\Models\ContractSignature;
use App\Crm\Models\Contact;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The only writer of contract_signatures.
 *
 * The hash is read once, outside the transaction, from the exact bytes
 * Storage holds for this Contract — never a re-render — because that read is
 * what pins the signature to the document a client actually saw. Writing the
 * row and dispatching ContractSigned happen together the same way BookProject
 * pairs Job creation with ProjectBooked: one DB::transaction for the write,
 * the event dispatched immediately after it returns.
 */
class SignContract
{
    public function __invoke(Contract $contract, Contact $contact, string $typedName, string $ip, ?string $userAgent): ContractSignature
    {
        // Idempotency guards the row, not the trigger — a doubled request
        // (a retried POST, two tabs) finds the signature already there
        // instead of failing on the unique index or writing a second one.
        $existing = $contract->signature()->first();

        if ($existing !== null) {
            return $existing;
        }

        abort_unless($contract->status === ContractStatus::Rendered && $contract->hasArtifact(), 422);

        $hash = hash('sha256', Storage::disk('local')->get($contract->artifact_path));
        $templateVersion = $contract->template->version;

        $signature = DB::transaction(function () use ($contract, $contact, $typedName, $hash, $templateVersion, $ip, $userAgent) {
            $signature = ContractSignature::create([
                'contract_id' => $contract->id,
                'contact_id' => $contact->id,
                'typed_name' => $typedName,
                'consented_at' => now(),
                'document_sha256' => $hash,
                'contract_template_version' => $templateVersion,
                'ip_address' => $ip,
                'user_agent' => $userAgent === null ? null : substr($userAgent, 0, 500),
            ]);

            // The studio's own list of who still has to sign, kept in step
            // with the evidence by the one writer of that evidence.
            $contract->signers()->where('contact_id', $contact->id)
                ->whereNull('signed_at')->update(['signed_at' => now()]);

            // actor is null, not $contact — AuditLog::actor_id is a foreign
            // key to staff users, and a Contact is never one. The same shape
            // App\Portal\Actions\ConsumeClientAccess uses.
            AuditLogger::record(
                event: 'contract.signed',
                context: ['contact' => $contact->public_id, 'template_version' => $templateVersion],
                subject: $contract->public_id,
                projectId: $contract->project_id,
            );

            return $signature;
        });

        ContractSigned::dispatch($contract, $signature);

        return $signature;
    }
}
