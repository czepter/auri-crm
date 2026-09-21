<?php

namespace App\Crm\Data;

use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Project;
use App\Crm\Support\CrmVocabulary;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One project as a line in a list — C-02 (#236), which is where the sidebar
 * finally got an entry for the part of the product that owns most of its
 * screens.
 *
 * The date is the primary shoot's, not the earliest: `is_primary` is a
 * setting, and the earliest shoot of a wedding is getting ready
 * (DesignatePrimaryShoot). Position comes from DeriveProjectPosition rather
 * than a second rule read off the lead, for the reason that class exists.
 */
#[TypeScript]
class ProjectRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $typeKey,
        public ?string $date,
        public ?string $venue,
        public string $position,
        public ?string $positionKey,
        public string $phase,
        public ?string $contact,
        public int $openTasks,
        public int $taskCount,
        public ?int $value,
        public ?string $lastAt,
    ) {}

    public static function fromProject(Project $project, DeriveProjectPosition $position): self
    {
        $shoot = $project->primaryShoot;
        $at = $position($project);
        $vocabulary = app(CrmVocabulary::class);
        $stage = $at->source === 'shoot' ? $vocabulary->byKey(StagePipeline::Project, (string) $at->value) : null;

        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->name,
            typeKey: $project->type->key,
            date: $shoot?->date?->format('Y-m-d'),
            venue: $shoot?->venue(),
            position: $at->label,
            positionKey: $stage?->key,
            phase: match (true) {
                $at->source === 'completed' => 'delivered',
                $stage !== null => $vocabulary->phase($stage),
                default => 'upcoming',
            },
            contact: $project->primaryContact()?->name,
            openTasks: (int) ($project->open_tasks_count ?? 0),
            taskCount: (int) ($project->tasks_count ?? 0),
            value: $project->latestQuote?->total,
            lastAt: $at->source === 'shoot'
                ? $position->leastAdvanced($project->shoots)?->status_entered_at?->toIso8601String()
                : null,
        );
    }
}
