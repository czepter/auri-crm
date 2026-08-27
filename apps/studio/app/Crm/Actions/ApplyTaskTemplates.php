<?php

namespace App\Crm\Actions;

use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\StageKind;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\TaskAnchors;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;

/**
 * Turns the studio's standing templates into the tasks a project owes when it
 * reaches a stage.
 *
 * A template belongs to one stage of one pipeline, and to one project type or
 * to all of them — the workflow the studio drew. Which day the task lands on is
 * TaskAnchors' answer, not this class's: the offset is kept alongside the
 * anchor so a shoot that moves takes its tasks along.
 *
 * Idempotent by two unique indexes on tasks — (task_template_id, shoot_id), and
 * (task_template_id, project_id) where the shoot is null — rather than by a
 * check here. Re-entering a stage is ordinary (a mis-drop on the board, and
 * corrections are allowed to go backwards), so the guarantee belongs where
 * every path meets it.
 */
class ApplyTaskTemplates
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly TaskAnchors $anchors,
    ) {}

    /** Booking is the project pipeline's first stage. */
    public function __invoke(Project $project): void
    {
        $this->forStage($project, StageKind::Project, ShootStatus::Booked->value);
    }

    public function forStage(Project $project, StageKind $kind, string $stage): void
    {
        $templates = TaskTemplate::query()
            ->active()
            ->forStage($project->type, $kind, $stage)
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        if ($kind === StageKind::Lead) {
            foreach ($templates as $template) {
                $this->applyOne($project, null, $template, 'stage:'.$stage);
            }

            return;
        }

        // Cancelled shoots owe nothing: the occasion is not happening.
        foreach ($project->shoots()->uncancelled()->get() as $shoot) {
            $applicable = $templates->filter(fn (TaskTemplate $t) => $t->appliesTo($shoot));

            // Two passes: every task exists before any of them is asked to
            // point at another. S-12's chains are declared on the templates in
            // any order, and a single pass would leave a task whose
            // predecessor had not been created yet with no dependency at all
            // — a chain silently flattened rather than refused.
            $created = [];

            foreach ($applicable as $template) {
                $created[$template->id] = $this->applyOne(
                    $project,
                    $shoot,
                    $template,
                    $template->anchor === DueAnchor::StageEntry
                        ? 'shoot:'.$shoot->public_id.':'.$stage
                        : 'shoot:'.$shoot->public_id,
                );
            }

            foreach ($applicable as $template) {
                if ($template->depends_on_template_id === null) {
                    continue;
                }

                // Only within this shoot: two shoots produced from the same
                // template set are two independent chains, and crossing them
                // would make the second wedding's briefing wait on the first
                // one's delivery.
                $predecessor = $created[$template->depends_on_template_id] ?? null;

                $created[$template->id]->update(['depends_on_task_id' => $predecessor?->id]);
            }
        }
    }

    /**
     * T-14's settings screen: what the active set would produce for a shoot
     * on the given date, without booking anything. Built on an unsaved Shoot
     * so it walks through the exact same appliesTo()/dueFor() calls a real
     * booking uses — the settings screen and a real booking can never
     * disagree about what a date produces, because there is only one place
     * that computes it.
     *
     * @return list<array{title: string, dueOn: ?string}>
     */
    public function preview(CarbonImmutable $date, ?ShootKind $kind = null): array
    {
        $shoot = new Shoot(['date' => $date, 'kind' => $kind]);

        return TaskTemplate::query()->active()->orderBy('sort_order')->get()
            ->filter(fn (TaskTemplate $template) => $template->appliesTo($shoot))
            ->map(fn (TaskTemplate $template) => [
                'title' => $template->title,
                'dueOn' => $template->dueFor($shoot, $this->settings)?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function applyOne(Project $project, ?Shoot $shoot, TaskTemplate $template, string $anchor): Task
    {
        // updateOrCreate rather than create: re-applying must not duplicate,
        // and must not silently reset a due date somebody moved by hand — so
        // only the columns the template owns are written on the way in.
        return $project->tasks()->updateOrCreate(
            ['task_template_id' => $template->id, 'shoot_id' => $shoot?->id],
            [
                'title' => $template->title,
                'audience' => $template->audience,
                'due_on' => $this->anchors->dueOn($project, $anchor, $template->day_offset)?->format('Y-m-d'),
                // The offset the template counted is kept, not spent: a shoot
                // that moves takes its tasks along, and a template applied to
                // a dateless shoot gets its day the moment one is entered.
                'due_anchor' => $anchor,
                'due_offset_days' => $template->day_offset,
            ],
        );
    }
}
