<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One project's place in production: the shoot that speaks for it, and enough
 * of the day to act on without opening the project.
 */
#[TypeScript]
class ProductionRowData extends Data
{
    public function __construct(
        public string $projectId,
        public string $projectName,
        public string $shootId,
        public ?string $shootLabel,
        public string $kind,
        public ?string $date,
        public ?string $callTime,
        public ?string $venue,
        public string $status,
        public string $statusLabel,
        public int $daysInStatus,
        public int $crewCount,
        public int $openTasks,
    ) {}
}
