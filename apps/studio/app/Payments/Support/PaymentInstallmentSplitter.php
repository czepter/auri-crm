<?php

namespace App\Payments\Support;

/**
 * The one place a plan total turns into N installment amounts —
 * App\Payments\Actions\CreatePaymentPlan calls this so the deterministic
 * split is never reinvented at the call site.
 *
 * Plain integer division plus a remainder, the same shape
 * App\Quotes\Support\DiscountCalculator uses for its own rounding: total
 * minor units do not always divide evenly by the installment count (500 over
 * 3 installments is 166.67 each), and whatever is left over after every
 * installment gets its even share goes entirely onto the LAST installment —
 * named here once so every caller and every test agrees which one, rather
 * than leaving it to iteration order or splitting it further across rows.
 * "Restbetrag" (the final balance) already reads as the installment that
 * absorbs whatever is left, so the deterministic choice matches the mockup's
 * own vocabulary for free.
 */
final class PaymentInstallmentSplitter
{
    /** @return list<int> exactly $count amounts, summing to exactly $total */
    public static function evenly(int $total, int $count): array
    {
        $base = intdiv($total, $count);
        $amounts = array_fill(0, $count, $base);
        $amounts[$count - 1] += $total - ($base * $count);

        return $amounts;
    }
}
