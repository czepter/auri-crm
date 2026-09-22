<?php

namespace App\Quotes\Actions;

use App\Catalog\Models\Package;
use App\Quotes\Enums\PricingMode;
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

        // Q-11's shares and Q-08's slider travel the same dead way the lines
        // do: copied out, no id, no reference back.
        $quote->paymentStages()->createMany($package->copyPaymentStages());

        // §4.1: der Modus reist mit dem Satz, sonst steht ein klassisches
        // Angebot mit Regler da — im Editor die eine Art, im PDF die andere.
        $quote->update([
            ...$package->only('hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours'),
            'pricing_mode' => $package->hourly_rate === null ? PricingMode::Classic : PricingMode::Choice,
            'showcase_paths' => $package->copyShowcasePaths($quote->showcaseDirectory()),
        ]);

        $quote->recalculateTotal();

        return $quote;
    }
}
