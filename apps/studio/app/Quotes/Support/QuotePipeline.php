<?php

namespace App\Quotes\Support;

use App\Models\AuditLog;
use App\Quotes\Models\Quote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What was still in play on a past day, read back from the audit trail.
 *
 * A quote's status only says what it is now. The trail records when it was
 * created, when it was accepted and when a revision replaced it, so the
 * pipeline on any day is the quotes created by then and neither accepted nor
 * superseded by then — the same rule the live pipeline applies to status.
 *
 * Totals are today's: a sent quote cannot change (Quote::booted), and a draft
 * edited since counts at its current sum.
 */
class QuotePipeline
{
    public static function asOf(CarbonInterface $at, Builder $projects): int
    {
        $by = fn (string $event) => AuditLog::query()
            ->where('event', $event)
            ->where('created_at', '<=', $at);

        return (int) Quote::query()
            ->whereIn('project_id', $projects)
            ->whereIn('public_id', $by('quote.created')->whereNotNull('subject')->select('subject'))
            ->whereNotIn('public_id', $by('quote.accepted')->whereNotNull('subject')->select('subject'))
            // NOT IN over a subquery yielding a NULL matches nothing, hence the guard.
            ->whereNotIn('public_id', $by('quote.revised')
                ->whereRaw("(context::jsonb)->>'predecessor' is not null")
                ->select(DB::raw("(context::jsonb)->>'predecessor'")))
            ->sum('total');
    }
}
