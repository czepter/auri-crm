<?php

namespace App\Quotes\Support;

use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Carbon\CarbonInterface;

/**
 * Accepted over sent, as a whole percentage — the one definition of that
 * figure in this codebase.
 *
 * S-11 put it on the Übersicht's fourth KPI tile; S-13's Auswertungen screen
 * shows the same number beside its own series. Two queries that agree today
 * is not the same thing as one query, so this class is the query and both
 * screens are callers.
 *
 * Both sides key on `sent_at`, never on `accepted_at`. A quote sent in month
 * eleven and accepted in month thirteen would otherwise count in a window its
 * own denominator is outside, and the figure could exceed 100%.
 *
 * Null rather than zero when nothing went out in the window: a percentage of
 * nothing is not zero percent, and a caller that renders 0% is lying about a
 * studio that simply had a quiet quarter.
 */
class QuoteAcceptanceRate
{
    public static function trailingTwelveMonths(): ?int
    {
        return self::between(now()->subYear(), now());
    }

    public static function between(CarbonInterface $from, CarbonInterface $to): ?int
    {
        $sent = Quote::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        if ($sent === 0) {
            return null;
        }

        $accepted = Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        return (int) round($accepted / $sent * 100);
    }
}
