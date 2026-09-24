<?php

namespace App\Contracts\Data;

use App\Contracts\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One Vertrag as a line on /vertraege. */
#[TypeScript]
class ContractRowData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public ?string $contact,
        public int $version,
        public string $state,
        public string $status,
        public ?string $sentOn,
        public ?string $signedOn,
        public ?string $deadlineOn,
        public bool $hasDocument,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        return new self(
            id: $contract->public_id,
            projectId: $contract->case->public_id,
            projectName: $contract->case->name,
            contact: $contract->case->contactNames(),
            version: $contract->version,
            state: $contract->state->value,
            status: $contract->status->value,
            sentOn: $contract->sent_at?->format('Y-m-d'),
            signedOn: $contract->signature?->consented_at->format('Y-m-d'),
            deadlineOn: $contract->sign_deadline_on?->format('Y-m-d'),
            hasDocument: $contract->hasArtifact(),
        );
    }
}
