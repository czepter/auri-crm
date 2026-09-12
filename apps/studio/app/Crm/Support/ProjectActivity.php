<?php

namespace App\Crm\Support;

use App\Crm\Enums\StagePipeline;
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
        $stages = app(CrmVocabulary::class);

        return AuditLog::query()
            ->where('project_id', $project->id)
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->filter(fn (AuditLog $log) => trans()->has("activity.{$log->event}"))
            ->map(fn (AuditLog $log) => [
                'event' => $log->event,
                // Without the target, two moves on one day read as one move logged twice.
                'label' => __("activity.{$log->event}", [
                    'to' => $stages->byKey(
                        str_starts_with($log->event, 'lead.') ? StagePipeline::Lead : StagePipeline::Project,
                        $log->context['to'] ?? '',
                    )?->name ?? $log->context['to'] ?? '',
                ]),
                'at' => $log->context['occurred_at'] ?? $log->created_at->toIso8601String(),
                'actorName' => $log->actor?->name,
            ])
            ->values()
            ->all();
    }
}
