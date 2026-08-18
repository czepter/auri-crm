<?php

namespace App\Crm\Data;

use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Models\Project;
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
        public ?string $date,
        public ?string $venue,
        public string $position,
        public ?string $contact,
    ) {}

    public static function fromProject(Project $project, DeriveProjectPosition $position): self
    {
        $shoot = $project->primaryShoot;

        return new self(
            id: $project->public_id,
            name: $project->name,
            date: $shoot?->date?->format('Y-m-d'),
            venue: $shoot?->venue(),
            position: $position($project)->label,
            contact: $project->primaryContact()?->name,
        );
    }
}
