<?php

namespace App\Quotes\Support;

use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\QuoteItem;

/**
 * #412. The Entgelt split by tax rate — § 14 Abs. 4 Nr. 7 UStG — computed in
 * one place, because three callers need exactly the same answer: the quote's
 * own VAT snapshot, the five quote layouts and the portal's quote page, and
 * App\Payments\Support\InvoiceFigures when it shares one installment out over
 * the rates of the quote behind it.
 *
 * Basis points throughout (1900 = 19 %), and the rounding rule is
 * DiscountCalculator's, so a single-rate quote produces the same `vat_amount`
 * this class replaced.
 */
final class VatLines
{
    /**
     * Net and VAT per rate, largest net first.
     *
     * The quote-level discount is shared out over the rates pro rata on their
     * discounted line totals, and the cent the division leaves over goes onto
     * the largest line — so the nets sum to the quote's own `net` exactly and
     * no rate is quietly rounded away.
     *
     * @param  iterable<QuoteItem>  $items
     * @return list<array{rate: int, net: int, vat: int}>
     */
    public static function fromItems(iterable $items, int $defaultRate, int $net): array
    {
        $bases = [];

        foreach ($items as $item) {
            $base = $item->discountedLineTotal();

            if ($base === 0 || ! $item->isCharged()) {
                continue;
            }

            $rate = $item->vat_rate ?? $defaultRate;
            $bases[$rate] = ($bases[$rate] ?? 0) + $base;
        }

        // Nothing charged on any line — an all-inclusive quote, or one with
        // no lines yet. Still one row at the quote's rate, so a summary chain
        // does not lose its Umsatzsteuer line the moment the net is zero.
        if ($bases === []) {
            return [self::line($defaultRate, $net)];
        }

        arsort($bases);

        return self::shareOut($bases, $net);
    }

    /**
     * The same lines as gross shares of one amount, largest first — what
     * App\Payments\Support\InvoiceFigures divides an installment into. The
     * cent the division leaves over goes onto the largest share, so the
     * shares sum to the installment exactly.
     *
     * @param  list<array{rate: int, net: int, vat: int}>  $lines
     * @return array<int, int> rate => gross share
     */
    public static function grossShares(array $lines, int $gross): array
    {
        $weights = [];

        foreach ($lines as $line) {
            $weights[$line['rate']] = ($weights[$line['rate']] ?? 0) + $line['net'] + $line['vat'];
        }

        $total = array_sum($weights);

        if ($total <= 0) {
            return [];
        }

        arsort($weights);

        $shares = [];
        $spent = 0;

        foreach ($weights as $rate => $weight) {
            $shares[$rate] = intdiv($gross * $weight, $total);
            $spent += $shares[$rate];
        }

        $shares[array_key_first($shares)] += $gross - $spent;

        return $shares;
    }

    /** 1900 basis points as "19", for a "USt. 19 %" label. */
    public static function percent(int $rate): string
    {
        return rtrim(rtrim(number_format($rate / 100, 2, ',', '.'), '0'), ',');
    }

    /**
     * @param  array<int, int>  $bases  rate => discounted line total, largest first
     * @return list<array{rate: int, net: int, vat: int}>
     */
    private static function shareOut(array $bases, int $net): array
    {
        $subtotal = array_sum($bases);
        $out = [];
        $spent = 0;

        foreach ($bases as $rate => $base) {
            $share = $subtotal === 0 ? 0 : intdiv($net * $base, $subtotal);
            $out[] = self::line((int) $rate, $share);
            $spent += $share;
        }

        if ($spent !== $net && $out !== []) {
            $out[0] = self::line($out[0]['rate'], $out[0]['net'] + $net - $spent);
        }

        return $out;
    }

    /** @return array{rate: int, net: int, vat: int} */
    private static function line(int $rate, int $net): array
    {
        return [
            'rate' => $rate,
            'net' => $net,
            'vat' => DiscountCalculator::resolve($net, DiscountType::Percentage, $rate),
        ];
    }
}
