<?php

namespace App\Quotes\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
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

    public static function between(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): ?int
    {
        ['sent' => $sent, 'accepted' => $accepted] = self::counts($from, $to, $type);

        return $sent === 0 ? null : (int) round($accepted / $sent * 100);
    }

    /**
     * The two figures the percentage is made of. C-05 (#239) needs them
     * unrounded: the Übersicht's tile reads `6 von 12` and draws one dot per
     * quote, and a percentage cannot be turned back into the cases behind it.
     *
     * @return array{sent: int, accepted: int}
     */
    public static function countsForTrailingYear(): array
    {
        return self::counts(now()->subYear(), now());
    }

    /** @return array{sent: int, accepted: int} */
    public static function counts(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $sent = Quote::query()
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to]);

        // Der Projekttyp-Filter der Auswertungen. Ohne Typ bleibt die Abfrage
        // die der Übersicht — dieselbe Zahl an beiden Stellen.
        if ($type !== null) {
            $sent->whereIn('quotes.case_id', ClientCase::query()->select('id')->where('type', $type->value));
        }

        return [
            'sent' => (clone $sent)->count(),
            'accepted' => (clone $sent)->where('status', QuoteStatus::Accepted)->count(),
        ];
    }
}
