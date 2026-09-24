<?php

namespace App\Crm\Actions;

use App\Crm\Data\CasePositionData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
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
 * Before that, the funnel speaks. The booking is the seam, because it is the
 * commitment: production has not started before it.
 *
 * C-06 originally wrote the fallback as "when no uncancelled shoot exists",
 * and that was unreachable. Intake creates the primary shoot at `requested`
 * and it stays there for the project's whole pre-booking life, so every
 * unbooked project reported "Angefragt" whatever the funnel was doing — and
 * contradicted the board, which reads the stage directly. `requested` is not a
 * pipeline position; it means the pipeline has not begun.
 */
class DeriveCasePosition
{
    public function __invoke(ClientCase $project): CasePositionData
    {
        $lead = $project->lead;

        // A closed case outranks everything. The shoots of a dead pursuit are not
        // a position the studio is working through.
        if ($project->isClosed()) {
            return new CasePositionData(source: 'lost', value: 'lost', label: $project->isBooked() ? 'Storniert' : 'Verloren');
        }

        // The studio's word, over whatever the shoots still say.
        if ($project->isCompleted()) {
            return new CasePositionData(source: 'completed', value: 'completed', label: 'Abgeschlossen');
        }

        if ($project->isBooked()) {
            $shoot = $this->leastAdvanced($project->shoots()->uncancelled()->get());

            if ($shoot !== null) {
                return new CasePositionData(
                    source: 'shoot',
                    value: $shoot->status->key,
                    label: $shoot->status->name,
                );
            }

            // Booked, but every shoot cancelled. The funnel below still reads
            // "Gebucht", which remains the truthful answer.
        }

        if ($lead === null) {
            return new CasePositionData(source: 'none', value: null, label: '—');
        }

        return new CasePositionData(
            source: 'lead',
            value: $lead->stage->key,
            label: $lead->stage->name,
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
            ->sortBy(fn (Shoot $shoot) => $shoot->status->sort_order)
            ->first();
    }

    /**
     * A shooting day behind us whose status still says it lies ahead. The
     * status moves by hand, so this is how a project stalls without anyone
     * seeing it.
     *
     * @param  Collection<int, Shoot>  $shoots
     */
    public function stalled(Collection $shoots): ?Shoot
    {
        $shot = app(CrmVocabulary::class)->anchored(StagePipeline::Project, StageAnchor::Shot)?->sort_order;

        return $shot === null ? null : $shoots
            ->reject(fn (Shoot $shoot) => $shoot->isCancelled() || $shoot->date === null)
            ->filter(fn (Shoot $shoot) => $shoot->date->lt(today()) && $shoot->status->sort_order < $shot)
            ->sortBy('date')
            ->first();
    }
}
