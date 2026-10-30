<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row in the project queue: who, the day that matters, where it stands, and
 * — when it hangs — why and for how long.
 */
#[TypeScript]
class CaseQueueRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $date,
        public string $stage,
        public ?string $reason,
        public ?int $days,
        public bool $open,
    ) {}
}
