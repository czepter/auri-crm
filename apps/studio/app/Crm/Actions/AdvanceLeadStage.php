<?php

namespace App\Crm\Actions;

use App\Crm\Enums\LeadStage;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Lead;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;

/**
 * The only writer of Lead::$stage and Lead::$lost_at.
 *
 * No transitions table: the ledger is audit_logs, which P1a already made
 * append-only, and stage_entered_at holds the one derived fact the board needs.
 * Guarding here rather than in a controller means the board, the capture form
 * and any future automation all get the same rules.
 */
class AdvanceLeadStage
{
    public function __construct(private readonly BookProject $book) {}

    public function __invoke(Lead $lead, LeadStage $next, ?User $actor = null): Lead
    {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is lost and cannot advance.");
        }

        $from = $lead->stage;

        if (! $from->canTransitionTo($next)) {
            throw new DomainException(
                "Illegal lead transition {$from->value} -> {$next->value}."
            );
        }

        // Reset on a correction too. The clock the board colours is "how long
        // has it sat where it sits", and the stage it returns to has no
        // recorded entry time to restore — only audit_logs knows, and reading
        // it back would be a query in the write path for a number that is
        // seconds old whenever a mis-drop is fixed the moment it happens.
        // ponytail: last-entry lookup from audit_logs if corrections start
        // arriving days late and the staleness colour matters that far back.
        $lead->forceFill([
            'stage' => $next,
            'stage_entered_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'lead.stage_changed',
            actor: $actor ?? auth('staff')->user(),
            context: ['from' => $from->value, 'to' => $next->value],
            subject: $lead->public_id,
            projectId: $lead->project_id,
        );

        // "Gebucht" is the booking, not a label for one. Without this the
        // stage and the Job disagree, and the two lists that read them —
        // /anfragen by stage, /projekte by Job — each show the record the
        // other one is missing. Idempotent: BookProject writes nothing when a
        // Job already exists (the contract-and-deposit path got there first).
        if ($next === LeadStage::Booked) {
            ($this->book)($lead->project, $actor ?? auth('staff')->user());
        }

        LeadStageChanged::dispatch($lead, $from, $next);

        return $lead;
    }

    public function markLost(Lead $lead, string $reason, ?User $actor = null): Lead
    {
        if ($lead->isLost()) {
            throw new DomainException("Lead {$lead->public_id} is already lost.");
        }

        // The stage is deliberately left alone. Which stage a lead died in is
        // the number the funnel is measured on, and overwriting it here would
        // make every lost lead look identical in hindsight.
        $lead->forceFill([
            'lost_at' => now(),
            'lost_reason' => $reason,
        ])->save();

        AuditLogger::record(
            event: 'lead.lost',
            actor: $actor ?? auth('staff')->user(),
            context: ['stage' => $lead->stage->value, 'reason' => $reason],
            subject: $lead->public_id,
            projectId: $lead->project_id,
        );

        LeadLost::dispatch($lead, $reason);

        return $lead;
    }
}
