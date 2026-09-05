<?php

namespace App\Quotes\Actions;

use App\Catalog\Models\Package;
use App\Quotes\Models\Quote;

/**
 * Populates a new quote's items from a package's current lines.
 *
 * Package::copyItems() already returns a deep, disconnected copy — no id and
 * no package_id in it — so this only has to lay that array onto the quote and
 * total it. Nothing written here ever looks at the package again: editing or
 * deactivating it afterwards leaves every quote already seeded from it alone.
 */
class SeedQuoteFromPackage
{
    public function __invoke(Quote $quote, Package $package): Quote
    {
        $quote->items()->createMany(
            collect($package->copyItems())
                ->values()
                ->map(fn (array $item, int $index) => [...$item, 'sort_order' => $index])
                ->all()
        );

        $quote->recalculateTotal();

        return $quote;
    }
}
