<?php

namespace App\Crm\Actions;

use App\Crm\Enums\BookedVia;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of jobs.
 *
 * One transaction: create the job, advance every uncancelled shoot to booked,
 * write the audit row, dispatch. The shoots move through AdvanceShootStatus
 * rather than a mass update, so P-03's guard and its audit rows apply instead
 * of being bypassed by an UPDATE that knows nothing about the pipeline.
 *
 * Risk carried: this ships two sprints before S-06, so every staff role can
 * book a project until role policies land.
 */
class BookProject
{
    public function __construct(
        private readonly AdvanceShootStatus $advance,
        private readonly CrmVocabulary $stages,
    ) {}

    /**
     * Null when the project already has a job — the no-op writes nothing and
     * fires nothing.
     *
     * $actor is never inferred from the ambient session: the manual button
     * (ProjectController::book) already names it explicitly, and P-05b's
     * listener relies on null staying null even when it runs inside a
     * staff-authenticated request (e.g. the same request in which a staff
     * member marked an installment paid by hand) — a signature is a trigger
     * with no staff member behind it, and inferring one from whoever happened
     * to be logged in would misattribute the booking.
     */
    public function __invoke(
        Project $project,
        ?User $actor = null,
        BookedVia $via = BookedVia::Manual,
        ?CarbonInterface $at = null,
    ): ?Job {
        // Idempotency guards job creation, not the trigger. P-05b adds a second
        // entry point, and two triggers racing on one project is a live failure
        // mode rather than a theoretical one.
        if ($project->job()->exists()) {
            return null;
        }

        $booked = $this->stages->anchored(StagePipeline::Project, StageAnchor::Booked);

        $job = DB::transaction(function () use ($project, $actor, $via, $booked, $at) {
            $job = Job::create([
                'project_id' => $project->id,
                'booked_at' => $at ?? now(),
                'booked_by_id' => $actor?->id,
                'booked_via' => $via,
            ]);

            $project->shoots()->uncancelled()->get()
                // A shoot already past booked is left alone: booking must not
                // undo delivered work, and the action would reject the
                // backwards move anyway.
                ->filter(fn (Shoot $shoot) => $booked !== null && $shoot->status->canTransitionTo($booked))
                ->each(fn (Shoot $shoot) => ($this->advance)($shoot, $booked, $actor, $at));

            AuditLogger::record(
                event: 'project.booked',
                actor: $actor,
                context: ['via' => $via->value, 'job' => $job->public_id],
                subject: $project->public_id,
                projectId: $project->id,
            );

            return $job;
        });

        ProjectBooked::dispatch($project, $job);

        return $job;
    }
}
