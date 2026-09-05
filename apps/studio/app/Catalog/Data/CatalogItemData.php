<?php

namespace App\Catalog\Data;

use App\Catalog\Models\CatalogItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CatalogItemData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?int $unitPrice,
    ) {}

    public static function fromModel(CatalogItem $item): self
    {
        return new self(
            id: $item->public_id,
            title: $item->title,
            description: $item->description,
            unitPrice: $item->unit_price,
        );
    }
}
