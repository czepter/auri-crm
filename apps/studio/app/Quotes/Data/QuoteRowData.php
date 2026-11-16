<?php

namespace App\Quotes\Data;

use App\Quotes\Models\Quote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One quote as a line in the cross-project overview at /angebote — QuoteData carries a quote's full editor state, too much for a list of many. */
#[TypeScript]
class QuoteRowData extends Data
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public ?string $contact,
        public ?string $number,
        public string $status,
        public string $statusLabel,
        public int $total,
        public ?string $sentAt,
        public ?string $createdAt,
        public ?string $validUntil,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->public_id,
            projectId: $quote->case->public_id,
            projectName: $quote->case->name,
            contact: $quote->case->primaryContact()?->name,
            number: $quote->number,
            status: $quote->status->value,
            statusLabel: $quote->status->label(),
            total: $quote->gross(),
            sentAt: $quote->sent_at?->toIso8601String(),
            createdAt: $quote->created_at?->toIso8601String(),
            validUntil: $quote->valid_until?->toDateString(),
        );
    }
}
