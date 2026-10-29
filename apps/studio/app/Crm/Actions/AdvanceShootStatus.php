<?php

namespace App\Crm\Actions;

use App\Crm\Events\ShootCancelled;
use App\Crm\Events\ShootStatusChanged;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\StageHistory;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;

/**
 * The only writer of Shoot::$status and Shoot::$cancelled_at.
 *
 * No transitions table: the ledger is audit_logs, which P1a already made
 * append-only, and status_entered_at holds the one derived fact the board
 * needs. Guarding here rather than in a controller means the project screen,
 * BookCase and any future automation all get the same rules — BookCase
 * in particular routes through this rather than mass-updating the column.
 *
 * A correction backwards is the one move Stage::canTransitionTo refuses on
 * this pipeline, and it is refused there for BookCase's sake: booking a
 * project must never drag a delivered shoot back to gebucht. A move somebody
 * asks for by hand is a different thing from one a side effect makes, so it is
 * allowed here and guarded there.
 */
class AdvanceShootStatus
{
    /** $enteredAt backdates the move; absent it, a correction restores the moment it first stood there. */
    public function __invoke(Shoot $shoot, Stage $next, ?User $actor = null, ?CarbonInterface $enteredAt = null): Shoot
    {
        if ($shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is cancelled and cannot advance.");
        }

        $from = $shoot->status;
        $backwards = $next->sort_order < $from->sort_order;

        if (! $backwards && ! $from->canTransitionTo($next)) {
            throw new DomainException(
                "Illegal shoot transition {$from->key} -> {$next->key}."
            );
        }

        $entered = $enteredAt
            ?? ($backwards ? StageHistory::enteredAt('shoot.status_changed', $shoot->public_id, $next->key) : null)
            ?? now();

        $shoot->forceFill([
            'status' => $next,
            'status_entered_at' => $entered,
        ])->save();

        AuditLogger::record(
            event: 'shoot.status_changed',
            actor: $actor ?? auth('staff')->user(),
            context: [
                'from' => $from->key,
                'to' => $next->key,
                'entered_at' => $entered->toIso8601String(),
                ...$backwards ? ['reverted' => true] : [],
            ],
            subject: $shoot->public_id,
            projectId: $shoot->case_id,
            occurredAt: $enteredAt,
        );

        ShootStatusChanged::dispatch($shoot, $from, $next);

        return $shoot;
    }

    public function cancel(Shoot $shoot, string $reason, ?User $actor = null, ?CarbonInterface $cancelledAt = null): Shoot
    {
        if ($shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is already cancelled.");
        }

        // The status is deliberately left alone. Which position the shoot died
        // in is what the pipeline is measured on, and overwriting it here would
        // make every cancelled shoot look identical in hindsight.
        $shoot->forceFill([
            'cancelled_at' => $cancelledAt ?? now(),
            'cancelled_reason' => $reason,
        ])->save();

        AuditLogger::record(
            event: 'shoot.cancelled',
            actor: $actor ?? auth('staff')->user(),
            context: ['status' => $shoot->status->key, 'reason' => $reason],
            subject: $shoot->public_id,
            projectId: $shoot->case_id,
            occurredAt: $cancelledAt,
        );

        ShootCancelled::dispatch($shoot, $reason);

        return $shoot;
    }

    /**
     * Take back an absage. Same shape as AdvanceLeadStage::reopen and for the
     * same reason: isCancelled() bars every further move, so a shoot cancelled
     * by mistake is stuck for good without this. The status was never
     * overwritten, so clearing the two columns puts it back where it stood.
     */
    public function uncancel(Shoot $shoot, ?User $actor = null): Shoot
    {
        if (! $shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is not cancelled.");
        }

        $reason = $shoot->cancelled_reason;

        $shoot->forceFill([
            'cancelled_at' => null,
            'cancelled_reason' => null,
        ])->save();

        AuditLogger::record(
            event: 'shoot.uncancelled',
            actor: $actor ?? auth('staff')->user(),
            context: ['status' => $shoot->status->key, 'reason' => $reason],
            subject: $shoot->public_id,
            projectId: $shoot->case_id,
        );

        return $shoot;
    }
}
