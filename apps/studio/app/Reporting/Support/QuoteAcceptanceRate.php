<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Support\CrmVocabulary;
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
 * Both sides count leads captured in the window that reached the stage, the
 * same cohort as the funnel steps "Angebot raus" and "Angebot angenommen" on
 * Auswertungen, so the KPI and the funnel cannot disagree.
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

    /**
     * Leads captured in the window that reached the stage anchored as
     * "Angebot raus", and the share of those that reached "Angebot angenommen":
     * the very counts the funnel on Auswertungen prints for those two steps.
     *
     * @return array{sent: int, accepted: int}
     */
    public static function counts(CarbonInterface $from, CarbonInterface $to, ?ProjectType $type = null): array
    {
        $vocabulary = app(CrmVocabulary::class);
        $steps = collect(FunnelTimes::steps($from, $to, $type))->keyBy('key');

        return [
            'sent' => (int) ($steps[$vocabulary->anchored(StagePipeline::Lead, StageAnchor::QuoteSent)?->key]['leads'] ?? 0),
            'accepted' => (int) ($steps[$vocabulary->anchored(StagePipeline::Lead, StageAnchor::QuoteAccepted)?->key]['leads'] ?? 0),
        ];
    }
}
