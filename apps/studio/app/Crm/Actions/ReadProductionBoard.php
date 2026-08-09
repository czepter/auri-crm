<?php

namespace App\Crm\Actions;

use App\Crm\Data\ProductionRowData;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The production board: one row per project in production, carrying the shoot
 * that speaks for it.
 *
 * Derived rather than maintained as its own state, so the board cannot
 * disagree with the shoots it summarises. It writes nothing — a status change
 * shows on the next read because there is nothing else for it to show.
 *
 * Joined through `jobs`, which is what makes it a *production* board: before
 * the Job exists the project is a pursuit, and the funnel is where it belongs.
 * The same seam DeriveProjectPosition uses for one project.
 *
 * `distinct on` rather than a per-project query: eleven projects would be
 * eleven round trips, and the ordering the choice depends on is the same one
 * Postgres needs for `distinct on` anyway.
 */
class ReadProductionBoard
{
    /**
     * Scoped to what the viewer may see. A crew role's board is their own
     * shoots — refusing the whole screen would be the wrong shape, since they
     * do have work on it.
     *
     * @return Collection<int, ProductionRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        return Shoot::query()
            ->select(DB::raw('distinct on (shoots.project_id) shoots.*'))
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->when($viewer !== null, fn ($query) => $query->whereIn(
                'shoots.project_id',
                Project::query()->visibleTo($viewer)->select('projects.id'),
            ))
            ->with(['project', 'crew'])
            ->withCount(['tasks as open_tasks' => fn ($query) => $query->open()])
            ->uncancelled()
            // One row per project: `distinct on` keeps the first row of each
            // project group, so the ORDER BY below is what chooses it — the
            // least-advanced uncancelled shoot. The order lives in ShootStatus,
            // so the enum stays the single source of the pipeline order rather
            // than a CASE expression kept in sync with it by hand.
            ->orderBy('shoots.project_id')
            ->orderByRaw($this->pipelineOrder())
            ->get()
            // Sorted in PHP: `distinct on` fixes the first ORDER BY column to
            // project_id, so the board's own order cannot also be expressed
            // there without a subquery that buys nothing at this size.
            // One composite key rather than two: an undated shoot sorts after
            // every dated one, and the dates sort among themselves.
            ->sortBy(fn (Shoot $shoot) => $shoot->date === null
                ? '9'
                : '0'.$shoot->date->format('Y-m-d'))
            ->values()
            ->map($this->toRow(...));
    }

    /**
     * `array_position(array[...], status)` — the pipeline order as data, built
     * from the enum's declaration order.
     */
    private function pipelineOrder(): string
    {
        $cases = collect(ShootStatus::cases())
            ->map(fn (ShootStatus $status) => "'{$status->value}'")
            ->join(',');

        return "array_position(array[{$cases}]::text[], shoots.status)";
    }

    private function toRow(Shoot $shoot): ProductionRowData
    {
        return new ProductionRowData(
            projectId: $shoot->project->public_id,
            projectName: $shoot->project->name,
            shootId: $shoot->public_id,
            shootLabel: $shoot->label,
            kind: $shoot->kind->label(),
            date: $shoot->date?->format('Y-m-d'),
            callTime: $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            venue: $shoot->venue(),
            status: $shoot->status->value,
            statusLabel: $shoot->status->label(),
            daysInStatus: (int) $shoot->status_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
            crewCount: $shoot->crew->count(),
            openTasks: (int) $shoot->open_tasks,
        );
    }
}
