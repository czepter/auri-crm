<?php

namespace App\Quotes\Support;

use App\Quotes\Enums\DiscountType;

/**
 * The one place a percentage-or-amount discount turns into a concrete
 * minor-unit amount — QuoteItem::booted() calls this with the line's own
 * total as the base, and Quote::recalculateTotal() calls it again with the
 * sum of the already-line-discounted lines. Same function either way, so
 * nothing downstream re-derives "10%" a second, possibly different way.
 *
 * A percentage is basis points (0–10000). Rounding half-up here is the only
 * rounding either level ever performs on its own discount; the sums that
 * feed into and out of this function are plain integer addition, which
 * introduces no rounding of its own — see Quote::recalculateTotal().
 *
 * Always capped at the base: a discount can take a line or a quote to zero,
 * never below it, so net >= 0 holds by construction before the database's
 * own check constraint (see the quotes table migration) ever has to catch
 * anything that got here some other way.
 */
final class DiscountCalculator
{
    private const BASIS_POINTS = 10_000;

    public static function resolve(int $base, ?DiscountType $type, ?int $value): int
    {
        if ($type === null || $value === null || $base <= 0 || $value <= 0) {
            return 0;
        }

        $amount = match ($type) {
            DiscountType::Percentage => intdiv($base * $value + intdiv(self::BASIS_POINTS, 2), self::BASIS_POINTS),
            DiscountType::Amount => $value,
        };

        return max(0, min($amount, $base));
    }
}
