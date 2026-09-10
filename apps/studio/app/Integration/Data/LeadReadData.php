<?php

namespace App\Integration\Data;

use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A lead as I-02's read endpoints hand it to an api-surface token.
 * public_id only, for both the lead and the project it hangs off — never
 * either row's internal id.
 */
#[TypeScript]
class LeadReadData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $source,
        public ?string $sourceOther,
        public string $stage,
        public bool $isLost,
        public string $capturedAt,
        public ?string $lostAt,
        public string $updatedAt,
    ) {}

    /** Requires `project:id,public_id` already eager loaded — see CrmReadController. */
    public static function fromModel(Lead $lead): self
    {
        return new self(
            id: $lead->public_id,
            projectId: $lead->project->public_id,
            source: $lead->source->value,
            sourceOther: $lead->source_other,
            stage: $lead->stage->key,
            isLost: $lead->isLost(),
            capturedAt: $lead->captured_at->toIso8601String(),
            lostAt: $lead->lost_at?->toIso8601String(),
            updatedAt: $lead->updated_at->toIso8601String(),
        );
    }
}
