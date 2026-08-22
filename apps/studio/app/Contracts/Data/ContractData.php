<?php

namespace App\Contracts\Data;

use App\Contracts\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The project-contract tab's view of one render. Never carries the artifact itself — only where to fetch it once it exists. */
#[TypeScript]
class ContractData extends Data
{
    public function __construct(
        public string $id,
        public string $status,
        public string $statusLabel,
        public string $templateName,
        public int $templateVersion,
        public ?string $renderedAt,
        public ?string $downloadUrl,
    ) {}

    public static function fromModel(Contract $contract, string $downloadUrl): self
    {
        return new self(
            id: $contract->public_id,
            status: $contract->status->value,
            statusLabel: $contract->status->label(),
            templateName: $contract->template->name,
            templateVersion: $contract->template->version,
            renderedAt: $contract->rendered_at?->toIso8601String(),
            downloadUrl: $contract->hasArtifact() ? $downloadUrl : null,
        );
    }
}
