<?php

namespace App\Catalog\Data;

use App\Catalog\Models\PackageItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageItemData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public int $quantity,
        public ?int $unitPrice,
        // Q-09/Q-10: der Baustein-Teil einer Zeile.
        public bool $selectable = false,
        public bool $selected = false,
        public ?string $outcome = null,
        public bool $hasImage = false,
    ) {}

    public static function fromModel(PackageItem $item): self
    {
        return new self(
            id: $item->public_id,
            title: $item->title,
            description: $item->description,
            quantity: $item->quantity,
            unitPrice: $item->unit_price,
            selectable: $item->selectable,
            selected: $item->selected,
            outcome: $item->outcome,
            // Nur ob es eines gibt. Der Pfad bleibt auf dem Server: das
            // Formular schickt die id zurueck, und der Controller schlaegt
            // den Pfad an der eigenen Zeile nach.
            hasImage: $item->image_path !== null,
        );
    }
}
