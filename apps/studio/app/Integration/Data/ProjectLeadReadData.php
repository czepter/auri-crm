<?php

namespace App\Integration\Data;

use App\Crm\Models\Lead;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The lead as the single-project read hands it over. No id and no projectId:
 * it hangs inside the project it belongs to, where LeadReadData — what the
 * lead index returns — carries both because a page of leads has no such frame.
 */
#[TypeScript]
class ProjectLeadReadData extends Data
{
    public function __construct(
        public string $stage,
        public string $source,
        public string $capturedAt,
        public ?string $lostAt,
    ) {}

    public static function fromModel(Lead $lead): self
    {
        return new self(
            stage: $lead->stage->key,
            source: $lead->source->value,
            capturedAt: $lead->captured_at->toIso8601String(),
            lostAt: $lead->lost_at?->toIso8601String(),
        );
    }
}
