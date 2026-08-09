<?php

namespace App\Quotes\Data;

use App\Quotes\Models\Quote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QuoteData extends Data
{
    /** @param list<QuoteItemData> $items */
    public function __construct(
        public string $id,
        public string $status,
        public string $statusLabel,
        public string $currency,
        public ?string $validUntil,
        public int $total,
        public array $items,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->public_id,
            status: $quote->status->value,
            statusLabel: $quote->status->label(),
            currency: $quote->currency,
            validUntil: $quote->valid_until?->format('Y-m-d'),
            total: $quote->total,
            items: $quote->items->map(QuoteItemData::fromModel(...))->all(),
        );
    }
}
