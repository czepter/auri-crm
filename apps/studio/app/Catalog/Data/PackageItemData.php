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
    ) {}

    public static function fromModel(PackageItem $item): self
    {
        return new self(
            id: $item->public_id,
            title: $item->title,
            description: $item->description,
            quantity: $item->quantity,
            unitPrice: $item->unit_price,
        );
    }
}
