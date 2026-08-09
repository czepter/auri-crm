<?php

namespace App\Crm\Data;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Project;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The chrome every project tab carries: who this is, where it stands, and what
 * the tabs are.
 *
 * `tabs` is built on the server because a tab appears when the feature behind
 * it exists and never as an empty shell — and whether a feature exists is a
 * fact the server holds. Mockup 04 shows seven; sprint 3 has features behind
 * two of them.
 */
#[TypeScript]
class ProjectHeaderData extends Data
{
    /** @param  list<array{key: string, label: string, href: string}>  $tabs */
    public function __construct(
        public string $id,
        public string $name,
        public string $names,
        public string $type,
        public ProjectPositionData $position,
        public ?string $primaryDate,
        public int $shootCount,
        public bool $isBooked,
        // Decided on the server and sent as a fact, so a control this person
        // may not use never reaches the markup at all.
        public bool $canBook,
        public bool $canManageShoots,
        public bool $canManageCrew,
        public bool $canManageTasks,
        public bool $canManageNotes,
        public array $tabs,
    ) {}

    public static function fromProject(Project $project, ProjectPositionData $position): self
    {
        $user = auth('staff')->user();
        return new self(
            id: $project->public_id,
            name: $project->name,
            names: $project->contacts
                ->sortBy(fn ($contact) => $contact->pivot->role === ProjectContactRole::Primary->value ? 0 : 1)
                ->pluck('name')
                ->join(' & ') ?: $project->name,
            type: $project->type->label(),
            position: $position,
            primaryDate: $project->primaryShoot?->date?->format('Y-m-d'),
            shootCount: $project->shoots->reject->isCancelled()->count(),
            isBooked: $project->job !== null,
            canBook: $user?->can('book', $project) ?? false,
            canManageShoots: $user?->can('manageShoots', $project) ?? false,
            canManageCrew: $user?->can('manageCrew', $project) ?? false,
            canManageTasks: $user?->can('manageTasks', $project) ?? false,
            canManageNotes: $user?->can('manageNotes', $project) ?? false,
            // Angebot and Vertrag are Q-*/M-* in P2, Zahlungen is M-03,
            // Fragebögen and Kommunikation are E-* in P4, Galerien is G-05 in
            // P3. Each appends a line here on the sprint it lands.
            tabs: [
                ['key' => 'overview', 'label' => 'Übersicht', 'href' => "/auftraege/{$project->public_id}"],
                ['key' => 'shoots', 'label' => 'Shootings', 'href' => "/auftraege/{$project->public_id}/shootings"],
            ],
        );
    }
}
