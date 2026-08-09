<?php

namespace App\Crm\Actions;

use App\Crm\Data\DateConflictData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;

/**
 * Vision §7: availability scans shoots.date for projects whose lead is not
 * lost. Joining through leads is what expresses "not lost" — a booked project
 * still has its lead, sitting at stage booked.
 *
 * This warns; it never blocks. The check answers "is somebody already on this
 * day", which is not the question "may this be saved". The studio breaks that
 * rule deliberately — a second shooter leads the smaller job, two half-days fit
 * one Saturday, the colliding date may still move or be lost — and a blocking
 * form would teach staff to type a wrong date to get past it, corrupting the
 * very column this reads.
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
     * @return array<string, list<DateConflictData>>  only dates that collide
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

        return $shoots
            ->groupBy(fn (Shoot $shoot) => $shoot->date->format('Y-m-d'))
            ->map(fn ($group) => $group->map(DateConflictData::fromShoot(...))->values()->all())
            ->all();
    }
}
