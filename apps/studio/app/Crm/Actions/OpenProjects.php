<?php

namespace App\Crm\Actions;

use App\Crm\Data\ProjectQueueRowData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;

/**
 * The booked projects still owed something, grouped by what the day asks of
 * them — OpenLeads' queue for the production side.
 *
 * "Hängt" is what nobody notices unless the pane says so: a shooting day
 * behind us whose status never moved, or a rate past due. The other three are
 * the calendar and the post-production in order. Delivered has left the queue.
 */
class OpenProjects
{
    private const GROUPS = ['haengt', 'woche', 'bearbeitung', 'spaeter'];

    public function __construct(
        private readonly DeriveCasePosition $position,
        private readonly CrmVocabulary $stages,
    ) {}

    /**
     * ponytail: every booked project on every project page, grouped in PHP —
     * fine at a studio's few hundred; past that, keep delivered ones out in SQL.
     *
     * @return list<array{key: string, rows: list<ProjectQueueRowData>}>
     */
    public function queue(User $user, ?ClientCase $open = null): array
    {
        $rows = ClientCase::query()
            ->visibleTo($user)
            ->booked()
            ->with(['contacts', 'shoots', 'primaryShoot', 'lead', 'paymentPlan.installments'])
            ->get()
            ->map(fn (ClientCase $project) => $this->row($project, $open))
            ->filter();

        return collect(self::GROUPS)->map(fn (string $key) => [
            'key' => $key,
            'rows' => $rows->where('group', $key)
                // Longest hanging first; everything else by its day, undated last.
                ->sortBy(fn (array $row) => $key === 'haengt' ? -$row['row']->days : ($row['row']->date ?? '9999-99-99'))
                ->pluck('row')
                ->values()
                ->all(),
        ])->all();
    }

    public function count(User $user): int
    {
        return ClientCase::query()
            ->visibleTo($user)
            ->booked()
            ->with('shoots')
            ->get()
            ->filter(fn (ClientCase $project) => $this->isOpen($project))
            ->count();
    }

    private function isOpen(ClientCase $project): bool
    {
        $shoot = $this->position->leastAdvanced($project->shoots);

        return ! $project->isClosed()
            && ! $project->isCompleted()
            && ($shoot === null || $shoot->status->sort_order < $this->sortOf(StageAnchor::Delivered));
    }

    /** @return array{group: string, row: ProjectQueueRowData}|null */
    private function row(ClientCase $project, ?ClientCase $open): ?array
    {
        if (! $this->isOpen($project)) {
            return null;
        }

        $shoot = $this->position->leastAdvanced($project->shoots);

        $stalled = $this->position->stalled($project->shoots);
        $late = $project->paymentPlan?->installments
            ->filter(fn (PaymentInstallment $rate) => in_array($rate->status, [PaymentInstallmentStatus::Scheduled, PaymentInstallmentStatus::Invoiced], true)
                && $rate->due_on->lt(today()))
            ->sortBy('due_on')
            ->first();
        $next = $project->shoots
            ->reject(fn (Shoot $shoot) => $shoot->isCancelled() || $shoot->date === null || $shoot->date->lt(today()))
            ->sortBy('date')
            ->first();

        [$group, $since, $reason] = match (true) {
            $stalled !== null => ['haengt', $stalled->date, 'status'],
            $late !== null => ['haengt', $late->due_on, 'payment'],
            $next !== null && $next->date->lte(today()->addDays(7)) => ['woche', null, null],
            $shoot !== null && $shoot->status->sort_order >= $this->sortOf(StageAnchor::Shot) => ['bearbeitung', null, null],
            default => ['spaeter', null, null],
        };

        return ['group' => $group, 'row' => new ProjectQueueRowData(
            id: $project->public_id,
            name: $project->contactNames(),
            date: ($stalled ?? $next ?? $project->primaryShoot)?->date?->format('Y-m-d'),
            stage: $shoot?->status->name ?? ($this->position)($project)->label,
            reason: $reason,
            days: $since === null ? null : (int) $since->copy()->startOfDay()->diffInDays(today()),
            open: $open !== null && $project->is($open),
        )];
    }

    private function sortOf(StageAnchor $anchor): int
    {
        return $this->stages->anchored(StagePipeline::Project, $anchor)?->sort_order ?? PHP_INT_MAX;
    }
}
