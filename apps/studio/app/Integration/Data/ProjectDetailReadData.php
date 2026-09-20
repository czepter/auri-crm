<?php

namespace App\Integration\Data;

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Quotes\Data\QuoteData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What GET /projects/{project} answers with, and nothing else does:
 * ProjectReadData plus what a caller would otherwise have to page three more
 * endpoints for. The index keeps ProjectReadData — a contact join on every
 * row is the wrong price for reading one.
 *
 * Internal notes stay out: they hang off ManageNotes, and a token holding
 * ViewAllProjects must not reach them through a project.
 */
#[TypeScript]
class ProjectDetailReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public ?string $typeOther,
        public bool $portalVisible,
        public object $fields,
        public string $updatedAt,
        public ?ProjectLeadReadData $lead,
        public ?JobReadData $job,
        public int $openTaskCount,
        /** @var list<ProjectContactReadData> */
        public array $contacts,
        /** @var list<ShootReadData> */
        public array $shoots,
        /** @var list<QuoteData> */
        public array $quotes,
    ) {}

    public static function fromModel(Project $project): self
    {
        $project->loadMissing(['lead', 'job', 'contacts', 'shoots.crew.user', 'quotes.items', 'quotes.paymentStages', 'quotes.predecessor']);
        $project->loadCount(['tasks as open_task_count' => fn ($query) => $query->whereNull('completed_at')]);

        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->key,
            typeOther: $project->type_other,
            portalVisible: $project->portal_visible,
            fields: (object) ($project->field_values ?? []),
            updatedAt: $project->updated_at->toIso8601String(),
            lead: $project->lead === null ? null : ProjectLeadReadData::fromModel($project->lead),
            job: $project->job === null ? null : JobReadData::fromModel($project->job->setRelation('project', $project)),
            openTaskCount: (int) $project->open_task_count,
            contacts: $project->contacts->map(ProjectContactReadData::fromModel(...))->all(),
            shoots: $project->shoots
                ->map(fn (Shoot $shoot) => ShootReadData::fromModel($shoot->setRelation('project', $project)))
                ->all(),
            quotes: $project->quotes->map(QuoteData::fromModel(...))->all(),
        );
    }
}
