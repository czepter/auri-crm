<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Actions\ReadProductionBoard;
use App\Crm\Data\NextShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Quotes\Actions\ReadInquiryColumn;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Übersicht: an action worklist, not a status report.
 *
 * Mockup 01 shows four domain blocks. Three read from data P1b does not have —
 * quotes are Q-*, Stripe is M-04, galleries are G-*. Each arrives with its own
 * phase; building them now would mean inventing their contents, which is the
 * failure clickdummy §6 records for this exact screen. The same rule governs
 * the KPI row: money-led tiles wait for the money.
 */
class DashboardController extends Controller
{
    public function __invoke(ReadProductionBoard $board, ReadInquiryColumn $inquiries): Response
    {
        $viewer = auth('staff')->user();

        return Inertia::render('Uebersicht', [
            'today' => now()->format('Y-m-d'),
            'openLeads' => Lead::query()->open()->count(),
            'newLeadsThisWeek' => Lead::query()->open()->where('created_at', '>=', now()->subWeek())->count(),
            'nextShoot' => $this->nextShoot($viewer),
            'production' => $board($viewer),
            'tasks' => $this->tasks($viewer),
            'openTasks' => Task::query()->open()->whereIn('project_id', Project::query()->visibleTo($viewer)->select('projects.id'))->count(),
            'inquiries' => $inquiries($viewer),
        ]);
    }

    /**
     * The next shooting day the studio still has to work: today or later, not
     * cancelled, and only for a project that is actually booked — an unbooked
     * date is a request, not a commitment.
     */
    private function nextShoot(User $viewer): ?NextShootData
    {
        $shoot = Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->select('shoots.*')
            ->with(['project', 'crew.user', 'tasks'])
            ->whereIn('shoots.project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->first();

        return $shoot === null ? null : NextShootData::fromShoot($shoot);
    }

    /**
     * What the studio owes and has not done, most urgent first, six at most.
     *
     * The six is the cap, not one row per project: mockup 01 lists Meier &
     * Kowalski twice in the same block, because two different things are owed
     * on that wedding and each needs its own button. Collapsing to one row per
     * project reads well on a busy board and empties the screen on a studio
     * with a single project in production — which is every studio in its first
     * month, and the state this screen gets judged in.
     *
     * Undated tasks sort last: they are owed, but not owed on any given day.
     */
    private function tasks(User $viewer)
    {
        return TaskRowData::collect(
            Task::query()
                ->open()
                ->whereIn('project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
                ->byDueDate()
                ->with('project')
                ->limit(6)
                ->get()
                ->map(TaskRowData::fromTask(...))
                ->values()
        );
    }
}
