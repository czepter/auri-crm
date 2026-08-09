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
        public ?string $predecessorId,
        public string $status,
        public string $statusLabel,
        public string $currency,
        public ?string $validUntil,
        public ?string $number,
        public ?string $sentAt,
        public int $total,
        public ?string $discountType,
        public ?int $discountValue,
        public int $discountAmount,
        public int $net,
        // Q-04: null on every quote until Q-06 sends it and calls
        // Quote::snapshotVat() — read straight off the quote's own columns,
        // never re-derived from the tenant's live settings, so this is the
        // one place the screen has to look regardless of what the studio's
        // VAT mode is set to right now.
        public ?string $vatMode,
        public ?int $vatRate,
        public ?string $vatNote,
        public ?int $vatAmount,
        public array $items,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->public_id,
            // The predecessor is a Quote row too, referenced here the same
            // way every other cross-model FK this Data layer exposes is —
            // by public_id, never the raw predecessor_id column.
            predecessorId: $quote->predecessor?->public_id,
            status: $quote->status->value,
            statusLabel: $quote->status->label(),
            currency: $quote->currency,
            validUntil: $quote->valid_until?->format('Y-m-d'),
            number: $quote->number,
            sentAt: $quote->sent_at?->toIso8601String(),
            total: $quote->total,
            discountType: $quote->discount_type?->value,
            discountValue: $quote->discount_value,
            discountAmount: $quote->discount_amount,
            net: $quote->net,
            vatMode: $quote->vat_mode,
            vatRate: $quote->vat_rate,
            vatNote: $quote->vat_note,
            vatAmount: $quote->vat_amount,
            items: $quote->items->map(QuoteItemData::fromModel(...))->all(),
        );
    }
}
