<?php

namespace App\Crm\Actions;

use App\Crm\Data\TaskTemplateData;
use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\ProjectType;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\StageKind;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\TaskTemplate;

/**
 * The workflow panel's payload: one column per stage of one pipeline, holding
 * the templates that stage owes for one project type.
 *
 * Both boards read it — the funnel from /anfragen, the pipeline from /projekte
 * — so the two can never drift into showing the same rows differently.
 */
class WorkflowColumns
{
    /** @return array<string, mixed> */
    public function __invoke(ProjectType $type, StageKind $kind): array
    {
        $templates = TaskTemplate::query()
            ->where('stage_kind', $kind)
            ->orderBy('sort_order')
            ->get()
            // Null project_type belongs to every column of every type.
            ->filter(fn (TaskTemplate $t) => $t->project_type === null || $t->project_type === $type);

        return [
            'type' => $type->value,
            'stageKind' => $kind->value,
            'types' => $this->options(ProjectType::cases()),
            'audiences' => $this->options(TaskAudience::cases()),
            'anchors' => $this->options(DueAnchor::cases()),
            'shootKinds' => $this->options(ShootKind::cases()),
            'columns' => array_map(fn (array $stage) => [
                'stage' => $stage['value'],
                'label' => $stage['label'],
                'templates' => TaskTemplateData::collect(
                    $templates->where('stage', $stage['value'])->values()
                ),
            ], $kind->stages()),
        ];
    }

    /**
     * @param  list<\App\Crm\Enums\HasLabel&\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
