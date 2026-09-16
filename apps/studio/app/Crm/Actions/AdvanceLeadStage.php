<?php

namespace App\Crm\Actions;

use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Models\User;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\StageHistory;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of Lead::$stage, Lead::$lost_at and Lead::$outcome.
 *
 * No transitions table: the ledger is audit_logs, which P1a already made
 * append-only, and stage_entered_at holds the one derived fact the board needs.
 * Guarding here rather than in a controller means the board, the capture form
 * and any future automation all get the same rules.
 */
class AdvanceLeadStage
{
    public function __construct(
        private readonly BookProject $book,
        private readonly UnbookProject $unbook,
        private readonly AdvanceShootStatus $shoots,
        private readonly CrmVocabulary $stages,
    ) {}

    /**
     * The funnel following a quote on its own. Forward only and only for an
     * open lead: a card staff already dragged further, or closed, stays where
     * they put it. A project without a lead has no funnel to move.
     */
    public function reach(?Lead $lead, StageAnchor $anchor, ?User $actor = null, ?CarbonInterface $at = null): void
    {
        $next = $this->stages->anchored(StagePipeline::Lead, $anchor);

        if ($lead === null || $next === null || $lead->isLost() || $next->sort_order <= $lead->stage->sort_order) {
            return;
        }

        $this($lead, $next, $actor, $at);
    }

    /**
     * $enteredAt backdates the move — an inquiry worked by phone last week and
     * entered today. Absent it, a correction restores the moment the lead
     * originally entered the stage it returns to, and a forward move is now.
     */
    public function __invoke(Lead $lead, Stage $next, ?User $actor = null, ?CarbonInterface $enteredAt = null): Lead
    {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is lost and cannot advance.");
        }

        $from = $lead->stage;
        $backwards = $next->sort_order < $from->sort_order;

        if (! $from->canTransitionTo($next)) {
            throw new DomainException(
                "Illegal lead transition {$from->key} -> {$next->key}."
            );
        }

        DB::transaction(function () use ($lead, $from, $next, $backwards, $actor, $enteredAt) {
            // Taking back the won stage takes back the booking with it, or the
            // board and the project list each show what the other one denies.
            // Throws when the booking has a payment plan — see UnbookProject.
            if ($backwards && $from->anchor === StageAnchor::Won) {
                ($this->unbook)($lead->project, $actor ?? auth('staff')->user(), $enteredAt);
            }

            // A correction restores the moment the lead first entered the stage it
            // returns to, read from the append-only trail: the board colours "how
            // long has it sat where it sits", and after a mis-drop is undone it has
            // sat there since it originally arrived, not since the undo.
            // T-20: a lead that moved forward starts the reminder ladder over. The
            // rungs count time in the stage it sits in, and it sits in a new one. A
            // correction that hands back an old timestamp keeps the rungs already
            // climbed, or undoing the mis-drop would re-send every reminder.
            $entered = $enteredAt
                ?? ($backwards ? StageHistory::enteredAt('lead.stage_changed', $lead->public_id, $next->key) : null)
                ?? now();

            $lead->forceFill([
                'stage' => $next,
                'stage_entered_at' => $entered,
                ...$backwards ? [] : ['stalled_reminders_sent' => 0, 'stalled_reminded_at' => null],
            ])->save();

            AuditLogger::record(
                event: 'lead.stage_changed',
                actor: $actor ?? auth('staff')->user(),
                context: [
                    'from' => $from->key,
                    'to' => $next->key,
                    'entered_at' => $entered->toIso8601String(),
                    ...$backwards ? ['reverted' => true] : [],
                ],
                subject: $lead->public_id,
                projectId: $lead->project_id,
                occurredAt: $enteredAt,
            );

            // "Gebucht" is the booking, not a label for one. Without this the
            // stage and the Job disagree, and the two lists that read them —
            // /anfragen by stage, /projekte by Job — each show the record the
            // other one is missing. Idempotent: BookProject writes nothing when
            // a Job already exists. Inside the transaction: a booking that
            // throws leaves the stage where it was instead of "Gebucht"
            // without a Job.
            if ($next->anchor === StageAnchor::Won) {
                ($this->book)($lead->project, $actor ?? auth('staff')->user(), at: $entered);
            }
        });

        LeadStageChanged::dispatch($lead, $from, $next);

        return $lead;
    }

    public function markLost(
        Lead $lead,
        string $reason,
        ?User $actor = null,
        LeadOutcome $outcome = LeadOutcome::Lost,
        ?CarbonInterface $lostAt = null,
    ): Lead {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is already lost.");
        }

        // The stage is deliberately left alone. Which stage a lead died in is
        // the number the funnel is measured on, and overwriting it here would
        // make every lost lead look identical in hindsight.
        $lostAt ??= now();

        $lead->forceFill([
            'lost_at' => $lostAt,
            'lost_reason' => $reason,
            'outcome' => $outcome,
        ])->save();

        // A lost project shoots nothing, and a date left standing on it still
        // reads as taken in the calendar and the range query.
        $lead->project->shoots()->uncancelled()->get()
            ->each(fn (Shoot $shoot) => $this->shoots->cancel($shoot, $reason, $actor, $lostAt));

        AuditLogger::record(
            event: 'lead.lost',
            actor: $actor ?? auth('staff')->user(),
            context: ['stage' => $lead->stage->key, 'reason' => $reason, 'outcome' => $outcome->value],
            subject: $lead->public_id,
            projectId: $lead->project_id,
            occurredAt: $lostAt,
        );

        LeadLost::dispatch($lead, $reason);

        return $lead;
    }

    /**
     * Closing a lead that was still alive is the one mistake markLost() cannot
     * be talked out of afterwards: isLost() bars every further move, so the
     * card is stuck for good. The stage was never overwritten, so there is
     * nothing to restore — clearing the three columns puts the lead back where
     * it stood.
     */
    public function reopen(Lead $lead, ?User $actor = null): Lead
    {
        if (! $lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is not closed.");
        }

        $reason = $lead->lost_reason;

        // ponytail: the close's own absagen are told apart by moment and
        // reason, so a shoot cancelled separately stays cancelled. Store the
        // cause on the shoot if the two ever coincide.
        $lead->project->shoots()
            ->where('cancelled_at', $lead->lost_at)
            ->where('cancelled_reason', $reason)
            ->get()
            ->each(fn (Shoot $shoot) => $this->shoots->uncancel($shoot, $actor));

        $lead->forceFill([
            'lost_at' => null,
            'lost_reason' => null,
            'outcome' => null,
        ])->save();

        AuditLogger::record(
            event: 'lead.reopened',
            actor: $actor ?? auth('staff')->user(),
            context: ['stage' => $lead->stage->key, 'reason' => $reason],
            subject: $lead->public_id,
            projectId: $lead->project_id,
        );

        return $lead;
    }
}
