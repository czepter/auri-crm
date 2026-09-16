<?php

namespace App\Quotes\Support;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use Illuminate\Support\Collection;

/**
 * Q-15: what a slider quote is worth before anybody has accepted it.
 *
 * A quote with an hourly rate has no price, it has a range. `total` is
 * deliberately not replaced — it stays empty until AcceptQuote writes it, so
 * every consumer downstream of the acceptance keeps reading exactly what it
 * read before. For the time in between, the board and the list need a
 * figure, and it is computed from the same configuration every time rather
 * than stored in a second column that would drift the moment somebody edits
 * a building block.
 */
final class QuoteRange
{
    /** The lowest the client can get to: the fewest hours and no optional block. */
    public static function min(Quote $quote): int
    {
        return self::totalFor($quote, $quote->min_hours ?? 0, collect());
    }

    /** The highest: the most hours and every optional block switched on. */
    public static function max(Quote $quote): int
    {
        return self::totalFor(
            $quote,
            $quote->max_hours ?? $quote->min_hours ?? 0,
            self::selectable($quote)->pluck('id'),
        );
    }

    /** What the screen opens on — the studio's own preselection. */
    public static function preselected(Quote $quote): int
    {
        return self::totalFor(
            $quote,
            $quote->hours ?? $quote->min_hours ?? 0,
            self::selectable($quote)->where('selected', true)->pluck('id'),
        );
    }

    /**
     * The one formula: hours at the rate, plus every fixed position, plus the
     * optional blocks named, less the quote-level discount. AcceptQuote computes the stored `total` through
     * here too, so the figure a client saw and the figure that lands on the
     * row can only ever come from one place.
     *
     * @param  Collection<int, int>  $selectedItemIds  primary keys of the switched-on blocks
     */
    public static function totalFor(Quote $quote, int $hours, Collection $selectedItemIds): int
    {
        $subtotal = self::subtotalFor($quote, $hours, $selectedItemIds);

        return $subtotal - DiscountCalculator::resolve($subtotal, $quote->discount_type, $quote->discount_value);
    }

    /**
     * totalFor() before the quote-level discount.
     *
     * @param  Collection<int, int>  $selectedItemIds
     */
    public static function subtotalFor(Quote $quote, int $hours, Collection $selectedItemIds): int
    {
        $items = $quote->items;

        $fixed = $items
            ->reject(fn (QuoteItem $item) => $item->isIncluded() || $item->selectable)
            ->sum(fn (QuoteItem $item) => $item->discountedLineTotal());

        $chosen = self::selectable($quote)
            ->filter(fn (QuoteItem $item) => $selectedItemIds->contains($item->id))
            ->sum(fn (QuoteItem $item) => $item->discountedLineTotal());

        return $hours * ($quote->hourly_rate ?? 0) + $fixed + $chosen;
    }

    /** @return Collection<int, QuoteItem> */
    public static function selectable(Quote $quote): Collection
    {
        return $quote->items->filter(fn (QuoteItem $item) => $item->selectable)->values();
    }
}
