<?php

namespace App\Catalog\Data;

use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
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
        public bool $portalOffered = false,
        public string $portalPriceMode = 'exact',
        public bool $portalRequiresDate = true,
        public ?string $projectTypeKey = null,
        public bool $portalHasImage = false,
        public ?int $price = null,
        public int $requestedCount = 0,
        public int $bookedCount = 0,
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
            portalOffered: $package->portal_offered,
            portalPriceMode: ($package->portal_price_mode ?? PortalPriceMode::Exact)->value,
            portalRequiresDate: $package->portal_requires_date,
            projectTypeKey: $package->project_type_key,
            portalHasImage: $package->portal_image_path !== null,
            // Die Zahl, nach der die Übersicht sortiert und die im Portal
            // steht. Aus den Positionen, nicht gespeichert: sonst wären es
            // zwei Wahrheiten.
            price: $items->sum(fn (PackageItem $item) => (int) $item->unit_price * $item->quantity),
            // Wie oft aus dem Portal angefragt und wie viel davon gebucht.
            // Wie items_count nur auf der Übersicht gezählt.
            requestedCount: (int) ($package->requested_count ?? 0),
            bookedCount: (int) ($package->booked_count ?? 0),
        );
    }
}
