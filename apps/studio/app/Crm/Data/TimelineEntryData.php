<?php

namespace App\Crm\Data;

use App\Crm\Models\TimelineEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One line of a shoot's Ablauf (E-04), as the project screen reads it. */
#[TypeScript]
class TimelineEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $time,
        public string $title,
        public ?string $location,
        public ?string $note,
        public bool $isTransit,
    ) {}

    public static function fromEntry(TimelineEntry $entry): self
    {
        return new self(
            id: $entry->public_id,
            // H:i, not the H:i:s the column returns — same trim ShootData
            // gives call_time and end_time.
            time: substr((string) $entry->time, 0, 5),
            title: $entry->title,
            location: $entry->location,
            note: $entry->note,
            isTransit: $entry->is_transit,
        );
    }
}
