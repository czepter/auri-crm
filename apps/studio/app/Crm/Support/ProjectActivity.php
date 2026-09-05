<?php

namespace App\Crm\Support;

use App\Crm\Models\Project;
use App\Models\AuditLog;

/**
 * A project's audit rows as something a screen can render.
 *
 * The whitelist below is the point of this class. An audit row carries
 * ip_address, user_agent and a context whose contents vary per event and can
 * include contact identifiers — none of which belongs in a page prop. Returning
 * arrays rather than models means a column added to audit_logs later cannot
 * silently widen the payload.
 *
 * An event with no entry in lang/de/activity.php is dropped, not rendered raw:
 * the label map doubles as the allow-list.
 */
class ProjectActivity
{
    public static function for(Project $project, int $limit = 25): array
    {
        return AuditLog::query()
            ->where('project_id', $project->id)
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->filter(fn (AuditLog $log) => trans()->has("activity.{$log->event}"))
            ->map(fn (AuditLog $log) => [
                'event' => $log->event,
                'label' => __("activity.{$log->event}"),
                'at' => $log->created_at->toIso8601String(),
                'actorName' => $log->actor?->name,
            ])
            ->values()
            ->all();
    }
}
