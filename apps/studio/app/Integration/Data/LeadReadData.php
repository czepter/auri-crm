<?php

namespace App\Integration\Data;

use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A lead as I-02's read endpoints hand it to an api-surface token. Both
 * id and projectId are the case's public_id (#520): a lead has none of its
 * own, and the row's internal id never leaves the server.
 */
#[TypeScript]
class LeadReadData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public ?string $source,
        public ?string $sourceOther,
        public string $stage,
        public ?string $stageEnteredAt,
        public bool $isLost,
        public string $capturedAt,
        public string $updatedAt,
    ) {}

    /** Requires `case:id,public_id,name,closed_at` already eager loaded — see CrmReadController. */
    public static function fromModel(Lead $lead): self
    {
        return new self(
            id: $lead->case->public_id,
            projectId: $lead->case->public_id,
            projectName: $lead->case->name,
            source: $lead->source?->value,
            sourceOther: $lead->source_other,
            stage: $lead->stage->key,
            stageEnteredAt: $lead->stage_entered_at?->toIso8601String(),
            isLost: $lead->isLost(),
            capturedAt: $lead->captured_at->toIso8601String(),
            updatedAt: $lead->updated_at->toIso8601String(),
        );
    }
}
