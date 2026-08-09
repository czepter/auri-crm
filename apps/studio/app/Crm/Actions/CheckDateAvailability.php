<?php

namespace App\Crm\Actions;

use App\Crm\Data\DateConflictData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Settings\Models\StudioClosure;

/**
 * Vision §7: availability scans shoots.date for projects whose lead is not
 * lost. Joining through leads is what expresses "not lost" — a booked project
 * still has its lead, sitting at stage booked.
 *
 * T-09 added a second source: studio_closures, the date ranges the studio is
 * closed. Both sources land in one DateConflictData shape — see that class —
 * so C-05's intake warning, P-02's board and S-04's calendar each keep asking
 * this one action a single question rather than querying two sources
 * themselves.
 *
 * This warns; it never blocks. The check answers "is somebody already on this
 * day", which is not the question "may this be saved". The studio breaks that
 * rule deliberately — a second shooter leads the smaller job, two half-days fit
 * one Saturday, the colliding date may still move or be lost — and a blocking
 * form would teach staff to type a wrong date to get past it, corrupting the
 * very column this reads. A closure conflict is exactly as informational: a
 * studio takes a wedding during Betriebsferien if it is the right wedding.
 */
class CheckDateAvailability
{
    /** @return list<DateConflictData> */
    public function forDate(?string $date, ?Project $except = null): array
    {
        if ($date === null) {
            return [];
        }

        return $this->forDates([$date], $except)[$date] ?? [];
    }

    /**
     * @param  list<string>  $dates  Y-m-d
     * @return array<string, list<DateConflictData>> only dates that collide
     */
    public function forDates(array $dates, ?Project $except = null): array
    {
        $dates = array_values(array_unique(array_filter($dates)));

        if ($dates === []) {
            return [];
        }

        // One query for the whole board. Calling forDate() per card is the
        // obvious implementation and is an N+1 nobody notices at eleven cards.
        $shoots = Shoot::query()
            ->with('project')
            ->whereIn('date', $dates)
            ->whereHas('project.lead', fn ($lead) => $lead->whereNull('lost_at'))
            ->when($except, fn ($query) => $query->where('project_id', '!=', $except->id))
            ->get();

        $conflicts = $shoots
            ->groupBy(fn (Shoot $shoot) => $shoot->date->format('Y-m-d'))
            ->map(fn ($group) => $group->map(DateConflictData::fromShoot(...))->values()->all())
            ->all();

        // A second query for the whole date range, never one per date: every
        // closure that could possibly cover any of $dates fits between the
        // earliest and the latest, and Y-m-d strings sort chronologically.
        $closures = StudioClosure::query()
            ->where('starts_on', '<=', max($dates))
            ->where('ends_on', '>=', min($dates))
            ->get();

        foreach ($dates as $date) {
            foreach ($closures as $closure) {
                if ($closure->coversDate($date)) {
                    $conflicts[$date] = [...($conflicts[$date] ?? []), DateConflictData::fromClosure($closure)];
                }
            }
        }

        return $conflicts;
    }
}
