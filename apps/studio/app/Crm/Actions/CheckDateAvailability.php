<?php

namespace App\Crm\Actions;

use App\Crm\Data\DateConflictData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Settings\Models\StudioClosure;

/**
 * Vision §7: availability scans shoots.date for projects whose lead is not
 * lost. Joining through leads is what expresses "not lost" — a booked project
 * still has its lead, sitting at stage booked.
 *
 * T-09 added a second source: studio_closures, the date ranges the studio is
 * closed. T-10 adds a third: absences, the date ranges one person is away.
 * Every source lands in one DateConflictData shape — see that class — so
 * C-05's intake warning, P-02's board and S-02's crew picker each keep asking
 * this one action a single question rather than querying three sources
 * themselves.
 *
 * This warns; it never blocks. The check answers "is somebody already on this
 * day", which is not the question "may this be saved". The studio breaks that
 * rule deliberately — a second shooter leads the smaller job, two half-days fit
 * one Saturday, the colliding date may still move or be lost — and a blocking
 * form would teach staff to type a wrong date to get past it, corrupting the
 * very column this reads. A closure or absence conflict is exactly as
 * informational: a studio takes a wedding during Betriebsferien, or assigns
 * someone on leave to a job that needs them, if it is the right call.
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
            ->uncancelled()
            ->whereHas('project.lead', fn ($lead) => $lead->open())
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

        // T-10's third source, same range-query shape as closures just
        // above: one query for the whole span, matched in PHP per date
        // rather than once per date.
        $absences = Absence::query()
            ->with('user')
            ->where('starts_on', '<=', max($dates))
            ->where('ends_on', '>=', min($dates))
            ->get();

        foreach ($dates as $date) {
            foreach ($absences as $absence) {
                if ($absence->coversDate($date)) {
                    $conflicts[$date] = [...($conflicts[$date] ?? []), DateConflictData::fromAbsence($absence)];
                }
            }
        }

        return $conflicts;
    }
}
