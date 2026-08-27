<?php

namespace App\Crm\Actions;

use App\Crm\Enums\ShootKind;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;

/**
 * Turns the studio's standing templates into the tasks a booked project owes.
 *
 * Offsets are relative to each shoot's own date rather than the project's: a
 * project with an engagement session and a wedding needs its "confirm timeline"
 * task against the shoot it belongs to. E-08 solves the same anchoring problem
 * generally, and this is the shape it will grow out of.
 *
 * Idempotent by the unique index on (task_template_id, shoot_id) rather than by
 * a check here. P-05b adds a second booking trigger and E-08 will re-apply
 * templates, so the guarantee belongs where both paths meet it.
 */
class ApplyTaskTemplates
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    public function __invoke(Project $project): void
    {
        $templates = TaskTemplate::query()->active()->orderBy('sort_order')->get();

        if ($templates->isEmpty()) {
            return;
        }

        // Cancelled shoots owe nothing: the occasion is not happening.
        $shoots = $project->shoots()->uncancelled()->get();

        foreach ($shoots as $shoot) {
            $applicable = $templates->filter(fn (TaskTemplate $t) => $t->appliesTo($shoot));

            // Two passes: every task exists before any of them is asked to
            // point at another. S-12's chains are declared on the templates in
            // any order, and a single pass would leave a task whose
            // predecessor had not been created yet with no dependency at all
            // — a chain silently flattened rather than refused.
            $created = [];

            foreach ($applicable as $template) {
                $created[$template->id] = $this->applyOne($project, $shoot, $template);
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
     * so it walks through the exact same appliesTo()/dueFor() calls __invoke()
     * uses on a real one — the settings screen and a real booking can never
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

    private function applyOne(Project $project, Shoot $shoot, TaskTemplate $template): Task
    {
        // updateOrCreate rather than create: re-applying must not duplicate,
        // and must not silently reset a due date somebody moved by hand — so
        // only the columns the template owns are written on the way in.
        return $project->tasks()->updateOrCreate(
            ['task_template_id' => $template->id, 'shoot_id' => $shoot->id],
            [
                'title' => $template->title,
                'audience' => $template->audience,
                'due_on' => $template->dueFor($shoot, $this->settings)?->format('Y-m-d'),
                // The offset the template counted is kept, not spent: a shoot
                // that moves takes its tasks along, and a template applied to
                // a dateless shoot gets its day the moment one is entered.
                'due_anchor' => 'shoot:'.$shoot->public_id,
                'due_offset_days' => $template->day_offset,
            ],
        );
    }
}
