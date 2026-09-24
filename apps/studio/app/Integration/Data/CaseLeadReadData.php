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
class CaseLeadReadData extends Data
{
    public function __construct(
        public string $stage,
        public ?string $source,
        public ?string $sourceOther,
        public ?string $inquiryText,
        public ?string $capturedVia,
        public ?string $capturedViaOther,
        public string $capturedAt,
        public ?string $stageEnteredAt,
    ) {}

    public static function fromModel(Lead $lead): self
    {
        return new self(
            stage: $lead->stage->key,
            source: $lead->source?->value,
            sourceOther: $lead->source_other,
            inquiryText: $lead->inquiry_text,
            capturedVia: $lead->captured_via?->value,
            capturedViaOther: $lead->captured_via_other,
            capturedAt: $lead->captured_at->toIso8601String(),
            stageEnteredAt: $lead->stage_entered_at?->toIso8601String(),
        );
    }
}
