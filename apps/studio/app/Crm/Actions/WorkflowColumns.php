<?php

namespace App\Crm\Actions;

use App\Crm\Data\TaskTemplateData;
use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\HasLabel;
use App\Crm\Enums\ShootKind;
use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Stage;
use App\Crm\Models\TaskTemplate;
use App\Crm\Support\CrmVocabulary;

/**
 * The workflow panel's payload: one column per stage of one pipeline, holding
 * the templates that stage owes for one project type.
 *
 * Both boards read it — the funnel from /anfragen, the pipeline from /projekte
 * — so the two can never drift into showing the same rows differently.
 */
class WorkflowColumns
{
    public function __construct(private readonly CrmVocabulary $vocabulary) {}

    /** @return array<string, mixed> */
    public function __invoke(ProjectType $type, StagePipeline $pipeline): array
    {
        $templates = TaskTemplate::query()
            ->where('pipeline', $pipeline)
            ->orderBy('sort_order')
            ->get()
            // Null project_type belongs to every column of every type.
            ->filter(fn (TaskTemplate $t) => $t->project_type === null || $t->project_type === $type->key);

        return [
            'type' => $type->key,
            'pipeline' => $pipeline->value,
            'audiences' => $this->options(TaskAudience::cases()),
            'anchors' => $this->options(DueAnchor::cases()),
            'shootKinds' => $this->options(ShootKind::cases()),
            'columns' => $this->vocabulary->ordered($pipeline)
                ->map(fn (Stage $stage) => [
                    'stage' => $stage->key,
                    'label' => $stage->name,
                    'templates' => TaskTemplateData::collect(
                        $templates->where('stage', $stage->key)->values()
                    ),
                ])
                ->all(),
        ];
    }

    /**
     * @param  list<HasLabel&\BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
