<?php

namespace App\Crm\Actions;

use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;

/**
 * The only writer of ClientCase::$completed_at.
 *
 * A mark beside the shoot statuses, not one of them: a project is done when
 * the studio says so, and a shoot left at "Bearbeitung" or one never moved
 * past "Gebucht" must not keep it open.
 */
class CompleteProject
{
    public function __invoke(ClientCase $project, ?User $actor = null): ClientCase
    {
        if ($project->isCompleted()) {
            throw new DomainException("Project {$project->public_id} is already completed.");
        }

        $project->forceFill(['completed_at' => now()])->save();

        AuditLogger::record(
            event: 'project.completed',
            actor: $actor ?? auth('staff')->user(),
            subject: $project->public_id,
            projectId: $project->id,
        );

        return $project;
    }

    public function reopen(ClientCase $project, ?User $actor = null): ClientCase
    {
        if (! $project->isCompleted()) {
            throw new DomainException("Project {$project->public_id} is not completed.");
        }

        $project->forceFill(['completed_at' => null])->save();

        AuditLogger::record(
            event: 'project.reopened',
            actor: $actor ?? auth('staff')->user(),
            subject: $project->public_id,
            projectId: $project->id,
        );

        return $project;
    }
}
