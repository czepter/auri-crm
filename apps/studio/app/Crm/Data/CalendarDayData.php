<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One cell of C-13's month grid (#249).
 *
 * `entries` holds what is on that day in reading order: shooting days first
 * with their time, then the all-day lines — an absence, a studio closure. A
 * free Saturday carries nothing at all, because a free Saturday is the most
 * important thing in the grid and it is not shown with text.
 */
#[TypeScript]
class CalendarDayData extends Data
{
    /** @param  list<CalendarEntryData>  $entries */
    public function __construct(
        public string $date,
        public bool $inMonth,
        public bool $today,
        public array $entries,
    ) {}
}
