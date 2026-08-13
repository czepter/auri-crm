<?php

namespace App\Crm\Actions;

use App\Crm\Models\Shoot;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Moves the "Haupttermin" designation from one shoot to another.
 *
 * A transaction rather than two updates, and in this order: the partial unique
 * index shoots_one_primary makes two primaries impossible, so setting the new
 * primary before clearing the incumbent violates it. The index is what makes
 * this an action instead of a controller line.
 */
class DesignatePrimaryShoot
{
    public function __invoke(Shoot $shoot, ?User $actor = null): Shoot
    {
        if ($shoot->isCancelled()) {
            throw new DomainException("Shoot {$shoot->public_id} is cancelled and cannot be the primary.");
        }

        if ($shoot->is_primary) {
            return $shoot;
        }

        return DB::transaction(function () use ($shoot, $actor) {
            $incumbent = $shoot->project->shoots()->where('is_primary', true)->first();

            // Clear first, always. The reverse order is the bug this action
            // exists to make unrepresentable.
            $incumbent?->forceFill(['is_primary' => false])->save();

            $shoot->forceFill(['is_primary' => true])->save();

            AuditLogger::record(
                event: 'shoot.primary_designated',
                actor: $actor ?? auth('staff')->user(),
                context: ['from' => $incumbent?->public_id],
                subject: $shoot->public_id,
                projectId: $shoot->project_id,
            );

            return $shoot;
        });
    }
}
