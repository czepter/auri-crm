<?php

namespace App\Catalog\Data;

use App\Catalog\Models\Package;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageData extends Data
{
    /** @param list<PackageItemData> $items */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public int $itemCount,
        public array $items,
    ) {}

    public static function fromModel(Package $package): self
    {
        $items = $package->items;

        return new self(
            id: $package->public_id,
            name: $package->name,
            description: $package->description,
            isActive: $package->is_active,
            // items_count comes from withCount('items') on the index query, so
            // that screen never loads every item just to show how many there
            // are. The edit screen loads the items anyway, and counts those.
            itemCount: (int) ($package->items_count ?? $items->count()),
            items: $items->map(PackageItemData::fromModel(...))->all(),
        );
    }
}
