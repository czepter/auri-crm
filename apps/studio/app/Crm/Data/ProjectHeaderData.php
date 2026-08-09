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
        public bool $portalVisible,
        public bool $canManagePortalVisibility,
        public bool $canManageQuotes,
        public bool $canManageContracts,
        public bool $canManagePaymentPlan,
        public bool $canManageForms,
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
            portalVisible: $project->portal_visible,
            canManagePortalVisibility: $user?->can('manageVisibility', $project) ?? false,
            canManageQuotes: $user?->can('manageQuotes', $project) ?? false,
            canManageContracts: $user?->can('manageContracts', $project) ?? false,
            canManagePaymentPlan: $user?->can('managePaymentPlan', $project) ?? false,
            canManageForms: $user?->can('manageForms', $project) ?? false,
            // Kommunikation (E-06) and Galerien (G-05, already in P3) each
            // append a line here on the sprint they land. Zahlungen (M-03)
            // does not: mockup 04-auftrag's own comment keeps it and
            // Aufgaben as "previews, compressed into the Übersicht tab rather
            // than built as full tab panels" (mockups/_shared/admin-extra.css)
            // — so it lives on the overview screen instead, never as a tab.
            //
            // Q-02 landed the quote half of "Angebot & Vertrag" under one
            // combined tab; M-01 splits Vertrag into its own tab now that it
            // has a screen of its own. Fragebögen (E-02) sits between
            // Shootings and Angebot, matching mockup 04's own tab order
            // (mockups/_shared/layout.js's PROJECT_TABS).
            tabs: [
                ['key' => 'overview', 'label' => 'Übersicht', 'href' => "/auftraege/{$project->public_id}"],
                ['key' => 'shoots', 'label' => 'Shootings', 'href' => "/auftraege/{$project->public_id}/shootings"],
                ['key' => 'forms', 'label' => 'Fragebögen', 'href' => "/auftraege/{$project->public_id}/frageboegen"],
                ['key' => 'quotes', 'label' => 'Angebot', 'href' => "/auftraege/{$project->public_id}/angebote"],
                ['key' => 'contract', 'label' => 'Vertrag', 'href' => "/auftraege/{$project->public_id}/vertrag"],
            ],
        );
    }
}
