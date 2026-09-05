<?php

namespace App\Catalog\Actions;

use App\Catalog\Models\Package;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;

/**
 * The way back from SeedQuoteFromPackage. It lives here rather than in Quotes
 * because it writes a Package — an action belongs where the thing it creates
 * belongs, not where the thing it reads does.
 *
 * Discounts do not travel: package_items has no such columns, and that is not
 * an oversight. A discount belongs to the negotiation, a service to the
 * catalogue. Validity, number and the VAT snapshot stay behind for the same
 * reason — they are properties of a document.
 */
class CreatePackageFromQuote
{
    public function __invoke(Quote $quote, string $name): Package
    {
        $package = Package::create(['name' => $name]);

        $package->items()->createMany(
            $quote->items->values()->map(fn (QuoteItem $item, int $index) => [
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'sort_order' => $index,
            ])->all()
        );

        return $package->load('items');
    }
}
