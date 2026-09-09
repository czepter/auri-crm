<?php

namespace App\Crm\Support;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Project;
use App\Crm\Models\Stage;
use App\Models\AuditLog;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;

/**
 * What a due date may hang off, and what day that turns out to be.
 *
 * Three keys, all resolved here: `shoot:<id>` is the shoot's own date,
 * `shoot:<id>:<status>` the day it entered that status, `stage:<value>` the
 * day the lead entered that stage. The offset is the one TaskTemplate already
 * counts in days against a shoot date — this widens what it may count from.
 */
final class TaskAnchors
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly CrmVocabulary $stages,
    ) {}

    /**
     * Every anchor this project can still hang a task on. `on` is null while
     * the anchor is ahead of the project — that is the whole difference
     * between a deadline and a promise.
     *
     * What the project can no longer reach is left out: a status a shoot
     * skipped past will never get a day, and a lost lead advances no further.
     *
     * @return list<array{value: string, label: string, group: string, on: ?string}>
     */
    public function forProject(Project $project): array
    {
        $reached = $this->reached($project);
        $options = [];

        foreach ($project->shoots as $shoot) {
            if ($shoot->isCancelled()) {
                continue;
            }

            $options[] = [
                'value' => 'shoot:'.$shoot->public_id,
                'label' => $shoot->label ?? $shoot->kind->label(),
                'group' => 'shoot',
                'on' => $shoot->date?->format('Y-m-d'),
            ];

            foreach ($this->stages->ordered(StagePipeline::Project) as $status) {
                $key = 'shoot:'.$shoot->public_id.':'.$status->key;
                $day = $reached[$key] ?? null;

                if ($day === null && ! $shoot->status->canTransitionTo($status)) {
                    continue;
                }

                $options[] = [
                    'value' => $key,
                    'label' => ($shoot->label ?? $shoot->kind->label()).' · '.$status->name,
                    'group' => $day === null ? 'pending' : 'reached',
                    'on' => $day?->format('Y-m-d'),
                ];
            }
        }

        $lead = $project->lead;

        foreach ($lead === null ? [] : $this->stages->ordered(StagePipeline::Lead) as $stage) {
            $key = 'stage:'.$stage->key;
            $day = $reached[$key] ?? null;

            // Ahead of the lead is still reachable; behind it and undated
            // means the funnel skipped it, and a lost lead reaches nothing.
            if ($day === null && ($lead->isLost() || $stage->sort_order < $lead->stage->sort_order)) {
                continue;
            }

            $options[] = [
                'value' => $key,
                'label' => $stage->name,
                'group' => $day === null ? 'pending' : 'reached',
                'on' => $day?->format('Y-m-d'),
            ];
        }

        return $options;
    }

    /** The day the anchor falls on, or null while it is still ahead. */
    public function resolve(Project $project, string $anchor): ?CarbonImmutable
    {
        $parts = explode(':', $anchor);

        if ($parts[0] === 'shoot' && count($parts) === 2) {
            return $project->shoots->firstWhere('public_id', $parts[1])?->date;
        }

        return $this->reached($project)[$anchor] ?? null;
    }

    /** The anchor plus its offset, rolled forward onto a day the studio works. */
    public function dueOn(Project $project, string $anchor, int $offsetDays): ?CarbonImmutable
    {
        $date = $this->resolve($project, $anchor);

        return $date === null
            ? null
            : self::workingDay($date->addDays($offsetDays), $this->settings);
    }

    /** How the anchor reads on a screen. Null once the shoot behind it is gone. */
    public static function label(Project $project, string $anchor): ?string
    {
        $parts = explode(':', $anchor);

        if ($parts[0] === 'stage') {
            return app(CrmVocabulary::class)->byKey(StagePipeline::Lead, $parts[1])?->name;
        }

        $shoot = $project->shoots->firstWhere('public_id', $parts[1]);

        if ($shoot === null) {
            return null;
        }

        $label = $shoot->label ?? $shoot->kind->label();

        return isset($parts[2])
            ? $label.' · '.(app(CrmVocabulary::class)->byKey(StagePipeline::Project, $parts[2])?->name ?? $parts[2])
            : $label;
    }

    /**
     * T-13's rule, shared with TaskTemplate::dueFor(): a deadline that lands
     * on a day the studio does not work rolls forward, never backward.
     */
    public static function workingDay(CarbonImmutable $date, StudioSettingsResolver $settings): CarbonImmutable
    {
        $workingDays = $settings->workingDays();

        while (! in_array($date->dayOfWeekIso, $workingDays, true)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * The day each anchor happened. audit_logs is the ledger — stage and
     * status columns hold only where a record sits now, and a task anchored
     * to "Angebot" needs the day the quote went out, not the day the lead
     * reached wherever it stands today.
     *
     * @return array<string, CarbonImmutable>
     */
    private function reached(Project $project): array
    {
        $dates = [];

        $logs = AuditLog::query()
            ->where('project_id', $project->id)
            ->whereIn('event', ['lead.stage_changed', 'shoot.status_changed'])
            ->orderBy('created_at')
            ->get();

        foreach ($logs as $log) {
            $key = $log->event === 'lead.stage_changed'
                ? 'stage:'.$log->context['to']
                : 'shoot:'.$log->subject.':'.$log->context['to'];

            // First entry wins: a correction that walks the funnel back and
            // forward again must not move a deadline that already passed. A
            // move entered after the fact carries the day it happened on;
            // without that the deadline would hang off the day it was typed.
            $dates[$key] ??= (isset($log->context['entered_at'])
                ? CarbonImmutable::parse($log->context['entered_at'])
                : $log->created_at)->startOfDay();
        }

        // Where the ledger has nothing — a record created straight into its
        // stage, or seeded data — the record's own columns answer.
        $lead = $project->lead;

        if ($lead !== null) {
            $dates['stage:'.$lead->stage->key] ??= ($lead->stage_entered_at ?? $lead->created_at)->toImmutable()->startOfDay();
            $dates['stage:'.$this->stages->anchored(StagePipeline::Lead, StageAnchor::Entry)?->key] ??= $lead->captured_at->toImmutable()->startOfDay();
        }

        foreach ($project->shoots as $shoot) {
            $key = 'shoot:'.$shoot->public_id.':';
            $dates[$key.$shoot->status->key] ??= ($shoot->status_entered_at ?? $shoot->created_at)->toImmutable()->startOfDay();
            $dates[$key.$this->stages->anchored(StagePipeline::Project, StageAnchor::Entry)?->key] ??= $shoot->created_at->toImmutable()->startOfDay();
        }

        return $dates;
    }
}
