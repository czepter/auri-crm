<?php

namespace App\Quotes\Data;

use App\Quotes\Models\QuoteItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QuoteItemData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public int $quantity,
        public ?int $unitPrice,
        // Sent alongside unitPrice rather than left for the client to derive:
        // whether a line is "Enthaltene Leistung" and what it contributes to
        // the total are both facts the server already knows for certain.
        public bool $isIncluded,
        public int $lineTotal,
        public ?string $discountType,
        public ?int $discountValue,
        public int $discountAmount,
        // lineTotal less discountAmount — sent alongside both rather than
        // left for the client to subtract, the same reasoning as lineTotal
        // itself above.
        public int $netTotal,
    ) {}

    public static function fromModel(QuoteItem $item): self
    {
        return new self(
            id: $item->public_id,
            title: $item->title,
            description: $item->description,
            quantity: $item->quantity,
            unitPrice: $item->unit_price,
            isIncluded: $item->isIncluded(),
            lineTotal: $item->lineTotal(),
            discountType: $item->discount_type?->value,
            discountValue: $item->discount_value,
            discountAmount: $item->discount_amount,
            netTotal: $item->discountedLineTotal(),
        );
    }
}
