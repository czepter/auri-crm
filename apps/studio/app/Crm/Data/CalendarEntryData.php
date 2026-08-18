<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line in a calendar day — C-13 (#249).
 *
 * `kind` is what the line is, not how it looks: `shoot` for a booked or
 * prepped day, `hold` for one still only requested (the accent, because an
 * unconfirmed date is the one thing on the grid somebody has to act on),
 * `away` for an absence and a studio closure alike. Whoever draws it decides
 * the shade.
 */
#[TypeScript]
class CalendarEntryData extends Data
{
    public function __construct(
        public string $kind,
        public ?string $time,
        public string $what,
        public ?string $who,
        public ?string $projectId,
    ) {}
}
