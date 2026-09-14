<?php

namespace App\Integration\Data;

use App\Crm\Models\TimelineEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TimelineEntryReadData extends Data
{
    public function __construct(
        public string $id,
        public string $time,
        public string $title,
        public ?string $location,
        public ?string $note,
        public bool $isTransit,
    ) {}

    public static function fromModel(TimelineEntry $entry): self
    {
        return new self(
            id: $entry->public_id,
            time: substr((string) $entry->time, 0, 5),
            title: $entry->title,
            location: $entry->location,
            note: $entry->note,
            isTransit: (bool) $entry->is_transit,
        );
    }
}
