<?php

namespace App\Catalog\Data;

use App\Catalog\Models\PackageItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Eine Zeile, wie ein Bestandskunde sie liest: was sie zusätzlich kostet, nur
 * wenn sie wählbar ist und (noch) nicht vorausgewählt — eine enthaltene oder
 * vorausgewählte Position steckt schon in PortalServiceData::$price, ein
 * zweiter Preis daneben wäre dieselbe Zahl zweimal.
 */
#[TypeScript]
class PortalServiceItemData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $selectable,
        public bool $selected,
        /** Nur bei einer wählbaren, nicht vorausgewählten Zeile — siehe oben. */
        public ?int $addOnPrice,
    ) {}

    public static function fromModel(PackageItem $item): self
    {
        return new self(
            title: $item->title,
            description: $item->description,
            selectable: $item->selectable,
            selected: $item->selected,
            addOnPrice: $item->selectable && ! $item->selected ? (int) $item->unit_price * $item->quantity : null,
        );
    }
}
