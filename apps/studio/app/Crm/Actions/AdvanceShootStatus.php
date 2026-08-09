<?php

namespace App\Crm\Actions;

use App\Crm\Enums\ShootStatus;
use App\Crm\Events\ShootCancelled;
use App\Crm\Events\ShootStatusChanged;
use App\Crm\Models\Shoot;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;

/**
 * The only writer of Shoot::$status and Shoot::$cancelled_at.
 *
 * No transitions table: the ledger is audit_logs, which P1a already made
 * append-only, and status_entered_at holds the one derived fact the board
 * needs. Guarding here rather than in a controller means the project screen,
 * BookProject and any future automation all get the same rules — BookProject
 * in particular routes through this rather than mass-updating the column.
 *
 * Correcting a mistaken forward transition has no path here, deliberately.
 * AdvanceLeadStage shipped with the identical hole.
 */
class AdvanceShootStatus
{
    public function __invoke(Shoot $shoot, ShootStatus $next, ?User $actor = null): Shoot
    {
        if ($shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is cancelled and cannot advance.");
        }

        $from = $shoot->status;

        if (! $from->canTransitionTo($next)) {
            throw new DomainException(
                "Illegal shoot transition {$from->value} -> {$next->value}."
            );
        }

        $shoot->forceFill([
            'status' => $next,
            'status_entered_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'shoot.status_changed',
            actor: $actor ?? auth('staff')->user(),
            context: ['from' => $from->value, 'to' => $next->value],
            subject: $shoot->public_id,
        );

        ShootStatusChanged::dispatch($shoot, $from, $next);

        return $shoot;
    }

    public function cancel(Shoot $shoot, string $reason, ?User $actor = null): Shoot
    {
        if ($shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is already cancelled.");
        }

        // The status is deliberately left alone. Which position the shoot died
        // in is what the pipeline is measured on, and overwriting it here would
        // make every cancelled shoot look identical in hindsight.
        $shoot->forceFill([
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ])->save();

        AuditLogger::record(
            event: 'shoot.cancelled',
            actor: $actor ?? auth('staff')->user(),
            context: ['status' => $shoot->status->value, 'reason' => $reason],
            subject: $shoot->public_id,
        );

        ShootCancelled::dispatch($shoot, $reason);

        return $shoot;
    }
}
