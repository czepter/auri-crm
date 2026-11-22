<?php

namespace App\Finance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One year's EÜR in cents. `vatMode` decides what the figures are: net with
 * the VAT on its own lines for `standard`, gross without VAT lines for
 * `small_business`.
 */
#[TypeScript]
class EuerReportData extends Data
{
    /** @param  list<array{key: string, label: string, euerLine: string, amount: int, count: int}>  $categories */
    public function __construct(
        public int $year,
        public string $vatMode,
        public ?string $lockedAt,
        public int $operatingIncome,
        public int $collectedVat,
        public int $assetSales,
        public int $income,
        public array $categories,
        public int $depreciation,
        public int $residualBookValue,
        public int $paidInputVat,
        public int $expenses,
        public int $profit,
        public EuerChecklistData $checklist,
    ) {}
}
