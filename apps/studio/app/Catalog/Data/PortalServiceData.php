<?php

namespace App\Catalog\Data;

use App\Catalog\Enums\PortalPriceMode;
use App\Catalog\Models\Package;
use App\Catalog\Models\PackageItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ein Paket, wie ein Bestandskunde es sieht. Nicht PackageData: das trägt
 * Positionspreise und den Aktiv-Schalter, also die Kalkulation und die
 * Verwaltung. Ein Kunde wählt eine Leistung danach, was drinsteckt und was
 * sie kostet — die eine Zahl, nicht ihre Herleitung.
 */
#[TypeScript]
class PortalServiceData extends Data
{
    /** @param list<string> $includes */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $text,
        public ?int $price,
        public string $priceMode,
        public bool $requiresDate,
        public bool $hasImage,
        public array $includes,
    ) {}

    public static function fromModel(Package $package): self
    {
        $mode = $package->portal_price_mode ?? PortalPriceMode::Exact;

        return new self(
            id: $package->public_id,
            name: $package->name,
            description: $package->description,
            text: $package->portal_text,
            // Auf Anfrage heißt: es steht keine Zahl da. Eine mitgelieferte,
            // die das Frontend verstecken soll, stünde im Netzwerk-Tab.
            price: $mode === PortalPriceMode::OnRequest ? null : $package->items->sum(
                fn (PackageItem $item) => (int) $item->unit_price * $item->quantity
            ),
            priceMode: $mode->value,
            requiresDate: $package->portal_requires_date,
            hasImage: $package->portal_image_path !== null,
            // Positionen ohne Preis sind enthaltene Leistungen und stehen
            // hier gleichrangig: was drin ist, ist drin.
            includes: $package->items
                ->map(fn (PackageItem $item) => trim($item->title.($item->description === null ? '' : ' · '.$item->description)))
                ->all(),
        );
    }
}
