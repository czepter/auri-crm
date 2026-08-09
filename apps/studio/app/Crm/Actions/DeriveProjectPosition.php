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
 * Once the project is in production, the least-advanced uncancelled shoot
 * speaks for it: a project is only as far along as its furthest-behind shoot,
 * so a delivered engagement session beside a booked wedding reads "Gebucht" —
 * the honest answer to what this project still owes the client.
 *
 * Before that, the funnel speaks. The Job is the seam, because the Job is the
 * commitment: production has not started until one exists.
 *
 * C-06 originally wrote the fallback as "when no uncancelled shoot exists",
 * and that was unreachable. Intake creates the primary shoot at `requested`
 * and it stays there for the project's whole pre-booking life, so every
 * unbooked project reported "Angefragt" whatever the funnel was doing — and
 * contradicted the board, which reads the stage directly. `requested` is not a
 * pipeline position; it means the pipeline has not begun.
 */
class DeriveProjectPosition
{
    public function __invoke(Project $project): ProjectPositionData
    {
        $lead = $project->lead;

        // A lost lead outranks everything. The shoots of a dead pursuit are not
        // a position the studio is working through.
        if ($lead?->isLost()) {
            return new ProjectPositionData(source: 'lost', value: 'lost', label: 'Verloren');
        }

        if ($project->job()->exists()) {
            $shoot = $this->leastAdvanced($project->shoots()->uncancelled()->get());

            if ($shoot !== null) {
                return new ProjectPositionData(
                    source: 'shoot',
                    value: $shoot->status->value,
                    label: $shoot->status->label(),
                );
            }

            // Booked, but every shoot cancelled. The funnel below still reads
            // "Gebucht", which remains the truthful answer.
        }

        if ($lead === null) {
            return new ProjectPositionData(source: 'none', value: null, label: '—');
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
