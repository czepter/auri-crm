<?php

namespace App\Crm\Actions;

use App\Crm\Data\CrewCandidateData;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * S-02's crew picker asks this before it offers anybody: on this shooting's
 * day, is that person free, away, or already standing on another shoot.
 *
 * Reads absences directly rather than through CheckDateAvailability: that
 * action composes name and category into one label on purpose, and a picker
 * that already prints the name needs the reason alone. The other-shoot half
 * has no source there at all — a DateConflictData names the colliding project,
 * never who is on it.
 */
class CrewAvailability
{
    /** @return array<string, list<CrewCandidateData>> keyed by shoot public_id */
    public function forProject(Project $project): array
    {
        $shoots = $project->shoots()->uncancelled()->get();
        $staff = User::query()->orderBy('name')->get();

        $dates = $shoots->pluck('date')->filter()
            ->map(fn ($date) => $date->format('Y-m-d'))->unique()->values()->all();

        if ($dates === []) {
            return $shoots->mapWithKeys(fn (Shoot $shoot) => [
                $shoot->public_id => $this->free($staff),
            ])->all();
        }

        $absences = Absence::query()
            ->where('starts_on', '<=', max($dates))
            ->where('ends_on', '>=', min($dates))
            ->get();

        $assignments = CrewAssignment::query()
            ->with('shoot.project')
            ->whereHas('shoot', fn ($query) => $query->uncancelled()->whereIn('date', $dates))
            ->get();

        return $shoots->mapWithKeys(function (Shoot $shoot) use ($staff, $absences, $assignments) {
            $date = $shoot->date?->format('Y-m-d');

            if ($date === null) {
                return [$shoot->public_id => $this->free($staff)];
            }

            $candidates = $staff->map(function (User $user) use ($shoot, $date, $absences, $assignments) {
                $absence = $absences->first(
                    fn (Absence $one) => $one->user_id === $user->id && $one->coversDate($date)
                );

                if ($absence !== null) {
                    return new CrewCandidateData(
                        id: $user->public_id,
                        name: $user->name,
                        status: 'absent',
                        note: $absence->category->label(),
                    );
                }

                $elsewhere = $assignments->first(fn (CrewAssignment $one) => $one->user_id === $user->id
                    && $one->shoot->id !== $shoot->id
                    && $one->shoot->date?->format('Y-m-d') === $date);

                if ($elsewhere !== null) {
                    $other = $elsewhere->shoot;

                    return new CrewCandidateData(
                        id: $user->public_id,
                        name: $user->name,
                        status: 'busy',
                        // The project names the clash unless it is this same
                        // project's own second shoot that day, where the name
                        // would say nothing.
                        note: $other->project_id === $shoot->project_id
                            ? ($other->label ?? $other->kind)
                            : $other->project->name,
                    );
                }

                return new CrewCandidateData(id: $user->public_id, name: $user->name, status: 'free');
            })->values()->all();

            return [$shoot->public_id => $candidates];
        })->all();
    }

    /** @param  Collection<int, User>  $staff */
    private function free(Collection $staff): array
    {
        return $staff->map(fn (User $user) => new CrewCandidateData(
            id: $user->public_id,
            name: $user->name,
            status: 'free',
        ))->values()->all();
    }
}
