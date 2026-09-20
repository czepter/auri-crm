<?php

namespace App\Contracts\Data;

use App\Contracts\Models\ContractSigner;
use App\Crm\Enums\ProjectContactRole;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractSignerData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $role,
        public ?string $signedAt,
        public ?string $remindedAt,
    ) {}

    public static function fromModel(ContractSigner $signer, ?ProjectContactRole $role): self
    {
        return new self(
            id: $signer->public_id,
            name: $signer->contact->name,
            role: $role?->label(),
            signedAt: $signer->signed_at?->toIso8601String(),
            remindedAt: $signer->reminded_at?->toIso8601String(),
        );
    }
}
