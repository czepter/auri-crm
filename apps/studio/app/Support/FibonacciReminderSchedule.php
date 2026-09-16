<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * When something that has not moved is worth a second look, in days since
 * the date it hangs off — a lead's stage entry, a task's due date, an
 * installment's due date.
 *
 * Fibonacci from 3, so the gap grows on its own: 3, 5, 8, 13, 21, 34. No
 * list to keep and no last rung to pick — the sequence thins out faster
 * than anyone loses patience, and whatever is still open on day 55 is a
 * decision nobody has made.
 */
final class FibonacciReminderSchedule
{
    public const FIRST_RUNG = 3;

    /**
     * Past $ceilingDays nothing counts as reached, so the item also leaves
     * every list that reads this — something open that long is parked, not
     * forgotten.
     */
    public static function reachedRungs(CarbonImmutable $anchor, ?CarbonImmutable $now = null, ?int $ceilingDays = null): int
    {
        // Day boundaries, not hours: an inquiry taken at 23:00 is not a day
        // old at midnight.
        $days = (int) $anchor->startOfDay()
            ->diffInDays(($now ?? CarbonImmutable::now())->startOfDay());

        if ($ceilingDays !== null && $days > $ceilingDays) {
            return 0;
        }

        $reached = 0;
        $previous = self::FIRST_RUNG - 1;
        $rung = self::FIRST_RUNG;

        while ($rung <= $days) {
            $reached++;
            [$previous, $rung] = [$rung, $previous + $rung];
        }

        return $reached;
    }

    public static function isDue(CarbonImmutable $anchor, int $remindersSent, ?CarbonImmutable $now = null, ?int $ceilingDays = null): bool
    {
        return self::reachedRungs($anchor, $now, $ceilingDays) > $remindersSent;
    }
}
