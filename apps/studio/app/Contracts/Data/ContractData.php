<?php

namespace App\Contracts\Data;

use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One Fassung as the project's contract tab reads it. Never carries the artifact itself — only where to fetch it once it exists. */
#[TypeScript]
class ContractData extends Data
{
    /**
     * @param  array<string, mixed>|null  $blocks
     * @param  list<ContractSignerData>  $signers
     */
    public function __construct(
        public string $id,
        public int $version,
        public string $state,
        public string $stateLabel,
        public string $status,
        public string $statusLabel,
        public string $templateName,
        public int $templateVersion,
        public string $body,
        public ?array $blocks,
        public ?string $renderedAt,
        public ?string $sentAt,
        public ?string $downloadUrl,
        // E-16: bis wann unterschrieben sein soll. Anker der Automations-
        // regel „Nachfassen vor Ablauf“ und sonst nirgends abgeleitet.
        public ?string $signDeadlineOn,
        public array $signers,
    ) {}

    /** @param  array<int, ProjectContactRole>  $roles  contact id => role on the project */
    public static function fromModel(Contract $contract, string $downloadUrl, array $roles = []): self
    {
        return new self(
            id: $contract->public_id,
            version: $contract->version,
            state: $contract->state->value,
            stateLabel: $contract->state->label(),
            status: $contract->status->value,
            statusLabel: $contract->status->label(),
            templateName: $contract->template->name,
            templateVersion: $contract->template->version,
            body: $contract->resolvedBody(),
            blocks: $contract->blocks ?? $contract->template->blocks,
            renderedAt: $contract->rendered_at?->toIso8601String(),
            sentAt: $contract->sent_at?->toIso8601String(),
            downloadUrl: $contract->hasArtifact() ? $downloadUrl : null,
            signDeadlineOn: $contract->sign_deadline_on?->format('Y-m-d'),
            signers: $contract->signers->map(
                fn ($signer) => ContractSignerData::fromModel($signer, $roles[$signer->contact_id] ?? null)
            )->values()->all(),
        );
    }
}
