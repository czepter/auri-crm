<?php

namespace App\Crm\Support;

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Enums\StageColor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ProjectField;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Stage;
use Illuminate\Support\Facades\Gate;

/**
 * What the stage overlay needs, shared by the two lists it opens over
 * (/anfragen and /projekte). Null for a role that may not configure any of
 * it — the screens hide the entry point on the same condition, and a payload
 * nobody may act on has no business on the wire.
 *
 * Costs nothing on top of those screens: both already read the funnel through
 * CrmVocabulary, which holds it for the request.
 */
class StageSettingsPayload
{
    public function __construct(private readonly CrmVocabulary $vocabulary) {}

    /** @return array<string, mixed>|null */
    public function __invoke(): ?array
    {
        if (Gate::denies('viewAny', Stage::class)) {
            return null;
        }

        return [
            'pipelines' => collect(StagePipeline::cases())->map(fn (StagePipeline $pipeline) => [
                'value' => $pipeline->value,
                'label' => $pipeline->label(),
                'stages' => $this->vocabulary->ordered($pipeline)->map(fn (Stage $stage) => [
                    'id' => $stage->public_id,
                    'key' => $stage->key,
                    'name' => $stage->name,
                    'color' => $stage->color->value,
                    'css' => $stage->color->css(),
                    'anchor' => $stage->anchor?->value,
                    'anchorLabel' => $stage->anchor?->label(),
                    'isActive' => $stage->is_active,
                ])->values()->all(),
            ])->all(),
            'types' => $this->vocabulary->types()->load('fields')->map(fn (ProjectType $type) => [
                'id' => $type->public_id,
                'key' => $type->key,
                'name' => $type->name,
                'isActive' => $type->is_active,
                'isDefault' => $type->is_default,
                // C-25: the definitions themselves, not a count — the pane
                // that lists them is the pane that edits them.
                'fields' => $type->fields->map(self::field(...))->values()->all(),
            ])->values()->all(),
            'fieldTypes' => collect(ProjectFieldType::cases())
                ->map(fn (ProjectFieldType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'hasOptions' => $type->hasOptions(),
                ])
                ->all(),
            'colors' => collect(StageColor::cases())
                ->map(fn (StageColor $color) => ['value' => $color->value, 'label' => $color->label(), 'css' => $color->css()])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function field(ProjectField $field): array
    {
        return [
            'id' => $field->public_id,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'typeLabel' => $field->type->label(),
            'options' => $field->options ?? [],
            'unit' => $field->unit,
            'required' => $field->required,
            'allowNewOptions' => $field->allow_new_options,
            'isActive' => $field->is_active,
        ];
    }
}
