<?php

namespace App\Crm\Actions;

use App\Crm\Data\ProjectPositionData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use Illuminate\Support\Collection;

/**
 * The one rule for where a project stands.
 *
 * Its own class on purpose. P-04's production board consumes the same rule in
 * sprint 4, and writing it inline in C-06's header is how the board ends up
 * deriving the project's position by a second, subtly different rule — exactly
 * the disagreement that deriving it rather than storing it was meant to
 * prevent.
 *
 * The least-advanced uncancelled shoot speaks for the project: a project is
 * only as far along as its furthest-behind shoot, so a delivered engagement
 * session beside a booked wedding reads "Gebucht" — the honest answer to what
 * this project still owes the client.
 */
class DeriveProjectPosition
{
    public function __invoke(Project $project): ProjectPositionData
    {
        $shoot = $this->leastAdvanced($project->shoots()->uncancelled()->get());

        if ($shoot !== null) {
            return new ProjectPositionData(
                source: 'shoot',
                value: $shoot->status->value,
                label: $shoot->status->label(),
            );
        }

        // No uncancelled shoot: before the first is created, or after every one
        // is cancelled. The funnel answers instead.
        $lead = $project->lead;

        if ($lead === null) {
            return new ProjectPositionData(source: 'none', value: null, label: '—');
        }

        if ($lead->isLost()) {
            return new ProjectPositionData(source: 'lost', value: 'lost', label: 'Verloren');
        }

        return new ProjectPositionData(
            source: 'lead',
            value: $lead->stage->value,
            label: $lead->stage->label(),
        );
    }

    /**
     * The rule itself, over shoots a caller has already loaded. P-04 hands it
     * one project's shoots at a time out of a single board query rather than
     * re-deriving the comparison in SQL.
     *
     * @param  Collection<int, Shoot>  $shoots
     */
    public function leastAdvanced(Collection $shoots): ?Shoot
    {
        return $shoots
            ->reject(fn (Shoot $shoot) => $shoot->isCancelled())
            ->sortBy(fn (Shoot $shoot) => $shoot->status->position())
            ->first();
    }
}
