<?php

namespace App\Settings\Data;

use App\Numbering\Actions\AllocateDocumentNumber;
use App\Settings\Models\NumberRange;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A number range as the settings screen sees it. `id` is the public_id, per
 * the Definition of Done. `nextNumberPreview` is T-04's "preview of the next
 * number to be issued, shown beside each range"; `canSetStartingCounter`
 * drives the screen disabling the counter field the moment a range has
 * issued anything for its current period, matching what an attempted write
 * would refuse server-side anyway.
 */
#[TypeScript]
class NumberRangeData extends Data
{
    public function __construct(
        public string $id,
        public string $documentType,
        public string $documentTypeLabel,
        public string $prefix,
        public string $resetRule,
        public int $startingCounter,
        public bool $canSetStartingCounter,
        public string $nextNumberPreview,
    ) {}

    public static function fromModel(NumberRange $range, AllocateDocumentNumber $allocator): self
    {
        return new self(
            id: $range->public_id,
            documentType: $range->document_type->value,
            documentTypeLabel: $range->document_type->label(),
            prefix: $range->prefix,
            resetRule: $range->reset_rule->value,
            startingCounter: $range->starting_counter,
            canSetStartingCounter: ! $range->hasIssued(),
            nextNumberPreview: $allocator->preview($range),
        );
    }
}
