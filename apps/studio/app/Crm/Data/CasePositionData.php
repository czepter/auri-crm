<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a project stands, derived rather than stored.
 *
 * `source` says which fact answered, because "Gebucht" read off a shoot and
 * "Gebucht" read off the lead stage mean different things to the studio — the
 * first is a shooting day on the calendar, the second is a funnel that got to
 * the end without one.
 */
#[TypeScript]
class CasePositionData extends Data
{
    public function __construct(
        /** shoot · lead · lost · completed · none */
        public string $source,
        public ?string $value,
        public string $label,
    ) {}
}
