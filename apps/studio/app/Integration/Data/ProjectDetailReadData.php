<?php

namespace App\Integration\Data;

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
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
        public bool $portalVisible,
        public object $fields,
        public string $updatedAt,
        public ?ProjectLeadReadData $lead,
        /** @var list<ProjectContactReadData> */
        public array $contacts,
        /** @var list<ShootReadData> */
        public array $shoots,
    ) {}

    public static function fromModel(Project $project): self
    {
        $project->loadMissing(['lead', 'contacts', 'shoots']);

        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->key,
            portalVisible: $project->portal_visible,
            fields: (object) ($project->field_values ?? []),
            updatedAt: $project->updated_at->toIso8601String(),
            lead: $project->lead === null ? null : ProjectLeadReadData::fromModel($project->lead),
            contacts: $project->contacts->map(ProjectContactReadData::fromModel(...))->all(),
            shoots: $project->shoots
                ->map(fn (Shoot $shoot) => ShootReadData::fromModel($shoot->setRelation('project', $project)))
                ->all(),
        );
    }
}
