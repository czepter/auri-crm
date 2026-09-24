<?php

namespace App\Notifications\Support;

use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who at the studio hears about something that happened on a project.
 *
 * The crew of its shoots where there is one, the whole studio otherwise —
 * not "the crew, and silence if nobody is assigned", which is how an
 * unstaffed inquiry would reach nobody at all. User::query() is tenant-scoped
 * by BelongsToTenant's global scope, the same as everything else on the
 * request.
 */
class StudioAudience
{
    /** @return Collection<int, User> */
    public function forCase(?ClientCase $project): Collection
    {
        $crew = $project === null
            ? collect()
            : User::query()
                ->whereIn('id', CrewAssignment::query()
                    ->whereIn('shoot_id', $project->shoots()->select('id'))
                    ->select('user_id'))
                ->get();

        return $crew->isNotEmpty() ? $crew : User::query()->get();
    }

    /**
     * T-24: the assignee where a task names one, the project's audience
     * otherwise. Three steps, descending in specificity, and the fallback is
     * the one above — a studio that assigns nothing sees no change.
     *
     * @return Collection<int, User>
     */
    public function forTask(Task $task): Collection
    {
        return $task->assignee !== null
            ? collect([$task->assignee])
            : $this->forCase($task->case);
    }
}
