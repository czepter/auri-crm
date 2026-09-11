<?php

namespace App\Crm\Data;

use App\Contracts\Enums\ContractStatus;
use App\Crm\Actions\OpenProjects;
use App\Crm\Actions\ReadStageTrack;
use App\Crm\Models\Project;
use App\Quotes\Enums\QuoteStatus;
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
    /** @param  list<array{key: string, label: string, href: string, count: int|null, countTo: int|null, children: list<array{key: string, label: string, translate: bool, href: string, count: int|null, reorderable: bool}>}>  $tabs
     * @param  list<array{key: string, label: string, pipeline: string, state: string}>  $track
     * @param  list<array{key: string, rows: list<ProjectQueueRowData>}>  $queue
     */
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
        public bool $canManageMessages,
        public bool $portalVisible,
        public bool $canManagePortalVisibility,
        public bool $canManageQuotes,
        public bool $canManageContracts,
        public bool $canManagePaymentPlan,
        public bool $canManageForms,
        public ?string $leadId,
        public ?string $outcomeLabel,
        public ?string $lostReason,
        public bool $canClose,
        public bool $canDelete,
        public array $tabs,
        public array $track,
        public array $queue,
    ) {}

    public static function fromProject(Project $project, ProjectPositionData $position): self
    {
        $user = auth('staff')->user();

        return new self(
            id: $project->public_id,
            name: $project->name,
            names: $project->contactNames(),
            type: $project->type->name,
            position: $position,
            primaryDate: $project->primaryShoot?->date?->format('Y-m-d'),
            shootCount: $project->shoots->reject->isCancelled()->count(),
            isBooked: $project->job !== null,
            canBook: $user?->can('book', $project) ?? false,
            canManageShoots: $user?->can('manageShoots', $project) ?? false,
            canManageCrew: $user?->can('manageCrew', $project) ?? false,
            canManageTasks: $user?->can('manageTasks', $project) ?? false,
            canManageNotes: $user?->can('manageNotes', $project) ?? false,
            canManageMessages: $user?->can('manageMessages', $project) ?? false,
            portalVisible: $project->portal_visible,
            canManagePortalVisibility: $user?->can('manageVisibility', $project) ?? false,
            canManageQuotes: $user?->can('manageQuotes', $project) ?? false,
            canManageContracts: $user?->can('manageContracts', $project) ?? false,
            canManagePaymentPlan: $user?->can('managePaymentPlan', $project) ?? false,
            canManageForms: $user?->can('manageForms', $project) ?? false,
            leadId: $project->lead?->public_id,
            outcomeLabel: $project->lead?->outcome?->label(),
            lostReason: $project->lead?->lost_reason,
            canClose: $project->lead !== null && ($user?->can('update', $project->lead) ?? false),
            canDelete: ($user?->can('delete', $project) ?? false) && $project->deletionBlockers()->isEmpty(),
            // Galerien (G-05, already in P3) appends a line here on the
            // sprint it lands. Zahlungen (M-03) does not: mockup
            // 04-auftrag's own comment keeps it and Aufgaben as "previews,
            // compressed into the Übersicht tab rather than built as full
            // tab panels" (mockups/_shared/admin-extra.css) — so it lives on
            // the overview screen instead, never as a tab.
            //
            // Q-02 landed the quote half of "Angebot & Vertrag" under one
            // combined tab; M-01 splits Vertrag into its own tab now that it
            // has a screen of its own. E-06's Kommunikation and E-02's
            // Fragebögen both sit between Shootings and Angebot, matching
            // mockup 04's own tab order (mockups/_shared/layout.js's
            // PROJECT_TABS).
            tabs: [
                ['key' => 'overview', 'label' => 'project.overview', 'href' => "/auftraege/{$project->public_id}", 'count' => null, 'countTo' => null, 'children' => []],
                ...($project->lead ? [['key' => 'lead', 'label' => 'project.lead', 'href' => "/anfragen/{$project->lead->public_id}", 'count' => null, 'countTo' => null, 'children' => []]] : []),
                ['key' => 'shoots', 'label' => 'project.shoots', 'href' => "/auftraege/{$project->public_id}/shootings", 'count' => null, 'countTo' => null, 'children' => []],
                ['key' => 'tasks', 'label' => 'project.tasks', 'href' => "/auftraege/{$project->public_id}/aufgaben", 'count' => $project->tasks()->whereNull('completed_at')->count(), 'countTo' => $project->tasks()->count(), 'children' => []],
                ['key' => 'galleries', 'label' => 'contacts.galleries', 'href' => "/auftraege/{$project->public_id}/galerien", 'count' => $project->galleries()->count(), 'countTo' => null, 'children' => []],
                ['key' => 'messages', 'label' => 'project.messages', 'href' => "/auftraege/{$project->public_id}/nachrichten", 'count' => self::waitingThreads($project), 'countTo' => null, 'children' => []],
                ['key' => 'forms', 'label' => 'project.forms', 'href' => "/auftraege/{$project->public_id}/frageboegen", 'count' => self::unansweredForms($project), 'countTo' => null, 'children' => []],
                ['key' => 'quotes', 'label' => 'project.quotes', 'href' => "/auftraege/{$project->public_id}/angebote", 'count' => $project->quotes()->where('status', QuoteStatus::Sent)->count(), 'countTo' => null, 'children' => []],
                ['key' => 'contract', 'label' => 'project.contract', 'href' => "/auftraege/{$project->public_id}/vertrag", 'count' => self::unsignedContracts($project), 'countTo' => null, 'children' => []],
            ],
            track: app(ReadStageTrack::class)($position),
            queue: $user === null ? [] : app(OpenProjects::class)->queue($user, $project),
        );
    }

    /**
     * The rule the inbox reads by: whoever wrote the last line owes the next
     * one. A thread the studio answered last is settled, and one with no
     * message at all has nobody owing anything.
     */
    private static function waitingThreads(Project $project): int
    {
        return $project->threads()
            ->whereHas('latestMessage', fn ($message) => $message->whereNotNull('contact_id'))
            ->count();
    }

    /**
     * Shared and untouched. A questionnaire the studio keeps for itself is
     * nobody's homework, and one answer anywhere on it means it has been
     * started.
     */
    private static function unansweredForms(Project $project): int
    {
        return $project->formInstances()
            ->shared()
            ->whereDoesntHave('fields.answers')
            ->count();
    }

    /**
     * In front of the client and unsigned. A contract that never rendered is
     * the studio's own problem and says nothing about what the client owes.
     */
    private static function unsignedContracts(Project $project): int
    {
        return $project->contracts()
            ->where('status', ContractStatus::Rendered)
            ->whereDoesntHave('signature')
            ->count();
    }
}
