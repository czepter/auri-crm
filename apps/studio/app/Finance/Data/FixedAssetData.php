<?php

namespace App\Finance\Data;

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Models\FixedAsset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The asset as the expense form edits it. */
#[TypeScript]
class FixedAssetData extends Data
{
    public function __construct(
        public string $id,
        public string $inventoryNo,
        public string $name,
        public string $acquiredAt,
        public int $costNet,
        public int $costGross,
        public DepreciationMethod $method,
        public ?int $usefulLifeMonths,
        public ?string $afaCategory,
        public ?string $disposedAt,
        public ?int $disposalProceeds,
    ) {}

    public static function fromModel(FixedAsset $asset): self
    {
        return new self(
            $asset->public_id,
            $asset->inventory_no,
            $asset->name,
            $asset->acquired_at->format('Y-m-d'),
            $asset->cost_net,
            $asset->cost_gross,
            $asset->method,
            $asset->useful_life_months,
            $asset->afa_category,
            $asset->disposed_at?->format('Y-m-d'),
            $asset->disposal_proceeds,
        );
    }
}
