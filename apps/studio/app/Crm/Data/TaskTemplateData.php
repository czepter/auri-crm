<?php

namespace App\Crm\Data;

use App\Crm\Models\TaskTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A task template as T-14's settings screen reads it.
 */
#[TypeScript]
class TaskTemplateData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public string $audience,
        public string $audienceLabel,
        public ?string $projectType,
        public string $stageKind,
        public string $stage,
        public int $dayOffset,
        public string $anchor,
        public string $anchorLabel,
        public ?string $shootKind,
        public ?string $shootKindLabel,
        public bool $isActive,
        public int $sortOrder,
    ) {}

    public static function fromModel(TaskTemplate $template): self
    {
        return new self(
            id: $template->public_id,
            title: $template->title,
            audience: $template->audience->value,
            audienceLabel: $template->audience->label(),
            projectType: $template->project_type?->value,
            stageKind: $template->stage_kind->value,
            stage: $template->stage,
            dayOffset: $template->day_offset,
            anchor: $template->anchor->value,
            anchorLabel: $template->anchor->label(),
            shootKind: $template->shoot_kind?->value,
            shootKindLabel: $template->shoot_kind?->label(),
            isActive: $template->is_active,
            sortOrder: $template->sort_order,
        );
    }
}
