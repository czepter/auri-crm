<?php

namespace App\Payments\Support;

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
 * Rounded to ten euros, not to the cent: these are shares of an offer, and a
 * client reading "1.075 €" beside "1.080 €" is reading an arithmetic
 * artefact. The last stage absorbs whatever the rounding moved, so the sum
 * is exact whatever the shares round to.
 */
final class PaymentStageSplitter
{
    private const ROUNDING = 1000;

    /**
     * @param  Collection<int, object{percent: int, minimum: int, label: string}>  $stages  in order
     * @return list<array{label: string, amount: int}> stages that got nothing are absent
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
                    max(self::roundToTenEuros((int) round($total * $stage->percent / 100)), $stage->minimum),
                    max(0, $rest),
                );

            $rest -= $amount;

            if ($amount > 0) {
                $amounts[] = ['label' => $stage->label, 'amount' => $amount];
            }
        }

        return $amounts;
    }

    private static function roundToTenEuros(int $minorUnits): int
    {
        return (int) round($minorUnits / self::ROUNDING) * self::ROUNDING;
    }
}
