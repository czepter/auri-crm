<?php

namespace App\Quotes\Support;

use Illuminate\Support\Collection;

/**
 * Q-11's distribution rule: percent, but never below a stage's own minimum.
 * What earlier stages take beyond their share is missing from the last one,
 * which always carries the rest. A stage with nothing left for it drops out.
 *
 * A function rather than a sum worked out where the figures are rendered,
 * and this is the reason: payment_installments carries a deferred constraint
 * trigger that rejects any commit whose amounts do not add up to the plan's
 * total. Anything that produces amounts has to be unable to break it, and
 * "unable" means one implementation with a test on both edges.
 *
 * Rounded to the euro, not to the cent: these are shares of an offer. Not to
 * ten euros either, which turns 50 % of 89 € into 40 €. The last stage absorbs
 * whatever the rounding moved, so the sum is exact whatever the shares round to.
 */
final class PaymentStageSplitter
{
    private const ROUNDING = 100;

    /**
     * @param  Collection<int, object{percent: int, minimum: int, label: string, note?: ?string, due_on?: mixed}>  $stages  in order
     * @return list<array{label: string, amount: int, note: ?string, due_on: mixed}> stages that got nothing are absent
     */
    public static function split(int $total, Collection $stages): array
    {
        $rest = $total;
        $last = $stages->count() - 1;
        $amounts = [];

        foreach ($stages->values() as $index => $stage) {
            $amount = $index === $last
                ? max(0, $rest)
                : min(
                    max(self::roundToEuros((int) round($total * $stage->percent / 100)), $stage->minimum),
                    max(0, $rest),
                );

            $rest -= $amount;

            if ($amount > 0) {
                $amounts[] = ['label' => $stage->label, 'amount' => $amount, 'note' => $stage->note ?? null, 'due_on' => $stage->due_on ?? null];
            }
        }

        return $amounts;
    }

    private static function roundToEuros(int $minorUnits): int
    {
        return (int) round($minorUnits / self::ROUNDING) * self::ROUNDING;
    }
}
