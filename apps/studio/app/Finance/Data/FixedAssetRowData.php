<?php

namespace App\Finance\Data;

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Models\FixedAsset;
use App\Finance\Support\Depreciation;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One asset on the Anlagen list, valued at the end of a year. */
#[TypeScript]
class FixedAssetRowData extends Data
{
    public function __construct(
        public string $id,
        public string $inventoryNo,
        public string $name,
        public string $acquiredAt,
        public DepreciationMethod $method,
        public ?int $usefulLifeMonths,
        public int $cost,
        public int $bookValue,
        public int $businessShare,
        public ?string $disposedAt,
        public ?int $disposalProceeds,
        public ?string $expenseId,
    ) {}

    public static function fromModel(FixedAsset $asset, int $year): self
    {
        return new self(
            $asset->public_id,
            $asset->inventory_no,
            $asset->name,
            $asset->acquired_at->format('Y-m-d'),
            $asset->method,
            $asset->useful_life_months,
            $asset->basis(),
            Depreciation::of($asset)->bookValue(CarbonImmutable::create($year, 12, 31)),
            $asset->business_share,
            $asset->disposed_at?->format('Y-m-d'),
            $asset->disposal_proceeds,
            $asset->expense?->public_id,
        );
    }
}
