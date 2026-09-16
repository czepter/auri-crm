<?php

namespace App\Crm\Actions;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\StageHistory;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The only deleter of jobs, and the counterpart to BookProject: a lead moved
 * onto the won stage by mistake left a Job behind when it was moved back, so
 * /anfragen and /projekte each showed a record the other one denied.
 *
 * Refused once money hangs off the booking. payment_plans.job_id cascades on
 * delete, so removing the job here would take the plan, its instalments and
 * their invoice numbers with it — silently, and a number issued once may not
 * be reused. Cancelling a booking that far along is a decision with paperwork,
 * not a correction of a mis-drop.
 *
 * What it does not undo: the tasks ApplyTemplatesOnBooking wrote, any
 * automation rule that fired. A
 * delivered event cannot be recalled, and a task somebody has since ticked is
 * theirs, not the booking's. Only the booking itself is taken back.
 */
class UnbookProject
{
    public function __construct(private readonly CrmVocabulary $stages) {}

    /** Null when there was no booking to take back — the no-op writes nothing. */
    public function __invoke(Project $project, ?User $actor = null, ?CarbonInterface $at = null): ?Job
    {
        $job = $project->job;

        if ($job === null) {
            return null;
        }

        if ($job->paymentPlan()->exists()) {
            throw new DomainException(
                "Auftrag {$project->public_id} hat einen Zahlungsplan und kann nicht entbucht werden."
            );
        }

        // Deleting a job is the booking capability, not the funnel's — a role
        // that may move cards but not book projects may not unbook one either
        // by dragging the card back. Null actor is the api surface's token
        // caller, already gated by its own scope on the route.
        if ($actor !== null && $actor->cannot('book', $project)) {
            throw new DomainException("Auftrag {$project->public_id} darf nicht entbucht werden.");
        }

        $booked = $this->stages->anchored(StagePipeline::Project, StageAnchor::Booked);
        $entry = $this->stages->anchored(StagePipeline::Project, StageAnchor::Entry);

        DB::transaction(function () use ($project, $actor, $job, $booked, $entry, $at) {
            // Only the shoots the booking itself moved. One that has since been
            // delivered stands on its own history and is left where it is.
            $project->shoots()->uncancelled()->get()
                ->filter(fn (Shoot $shoot) => $booked !== null && $shoot->status->is($booked))
                ->each(function (Shoot $shoot) use ($booked, $entry, $actor, $at) {
                    // Where the booking found it. Absent a trail — a shoot
                    // created straight into booked — the pipeline's entry.
                    $before = StageHistory::cameFrom('shoot.status_changed', $shoot->public_id, $shoot->status->key);
                    $back = $before === null ? $entry : $this->stages->byKey(StagePipeline::Project, $before);

                    $shoot->forceFill([
                        'status' => $back,
                        'status_entered_at' => ($back === null
                            ? null
                            : StageHistory::enteredAt('shoot.status_changed', $shoot->public_id, $back->key)) ?? now(),
                    ])->save();

                    AuditLogger::record(
                        event: 'shoot.status_changed',
                        actor: $actor,
                        context: [
                            'from' => $booked?->key,
                            'to' => $shoot->status->key,
                            'entered_at' => $shoot->status_entered_at?->toIso8601String(),
                            'reverted' => true,
                        ],
                        subject: $shoot->public_id,
                        projectId: $shoot->project_id,
                        occurredAt: $at,
                    );
                });

            AuditLogger::record(
                event: 'project.unbooked',
                actor: $actor,
                context: ['job' => $job->public_id, 'booked_at' => $job->booked_at->toIso8601String()],
                subject: $project->public_id,
                projectId: $project->id,
                occurredAt: $at,
            );

            $job->delete();
        });

        return $job;
    }
}
