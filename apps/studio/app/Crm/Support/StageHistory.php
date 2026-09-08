<?php

namespace App\Crm\Support;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;

/**
 * Reading the funnel backwards out of audit_logs.
 *
 * Both pipelines keep one derived fact on the row — stage_entered_at,
 * status_entered_at — and the full history in the append-only trail. That was
 * enough while movement was forwards only. Taking a move back needs two
 * answers the row cannot give: since when did it sit in the stage it is
 * returning to, and which stage did it come from. Both are in the trail
 * already, so this reads them there rather than adding a transitions table
 * that would duplicate it.
 *
 * ponytail: two indexed equality lookups per reversal, on a table nobody
 * queries in a loop. A covering index on (event, subject) if the trail grows
 * past what that carries.
 */
class StageHistory
{
    /**
     * When the subject last entered $stageKey. Null when the trail does not
     * reach back that far — a lead captured before the entry was written, or
     * one whose rows a retention sweep has taken.
     */
    public static function enteredAt(string $event, string $subject, string $stageKey): ?CarbonImmutable
    {
        $row = self::lastEntryInto($event, $subject, $stageKey);

        if ($row === null) {
            return null;
        }

        // A reversal restores an older timestamp than its own row carries, and
        // writes it into the context so a second reversal into the same stage
        // finds the original moment rather than the correction.
        return isset($row->context['entered_at'])
            ? CarbonImmutable::parse($row->context['entered_at'])
            : $row->created_at;
    }

    /** Which stage the subject stood in before it entered $stageKey. */
    public static function cameFrom(string $event, string $subject, string $stageKey): ?string
    {
        return self::lastEntryInto($event, $subject, $stageKey)?->context['from'] ?? null;
    }

    private static function lastEntryInto(string $event, string $subject, string $stageKey): ?AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('subject', $subject)
            ->where('context->to', $stageKey)
            ->latest('id')
            ->first();
    }
}
