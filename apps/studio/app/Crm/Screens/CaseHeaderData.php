<?php

namespace App\Crm\Screens;

use App\Contracts\Enums\ContractStatus;
use App\Crm\Actions\BuildThread;
use App\Crm\Actions\ReadStageTrack;
use App\Crm\Data\CasePersonData;
use App\Crm\Data\CasePositionData;
use App\Crm\Data\CaseQueueRowData;
use App\Crm\Data\ThreadEntryData;
use App\Crm\Models\ClientCase;
use App\Payments\Data\PaymentPlanData;
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
class CaseHeaderData extends Data
{
    /** @param  list<array{key: string, label: string, href: string, count: int|null, countTo: int|null, children: list<array{key: string, label: string, translate: bool, href: string, count: int|null, reorderable: bool}>}>  $tabs
     * @param  list<array{key: string, label: string, pipeline: string, state: string}>  $track
     * @param  list<array{key: string, rows: list<CaseQueueRowData>}>  $queue
     * @param  list<CasePersonData>  $people
     * @param  list<array{id: string, title: string|null, expiresAt: string|null}>  $galleries
     * @param  list<ThreadEntryData>  $history
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $names,
        public string $type,
        public CasePositionData $position,
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
        // #418: the one condition the "Zahlungsplan abwickeln" link hangs
        // off, beside the plan's own settled_at.
        public bool $primaryShootCancelled,
        public bool $canManageForms,
        public ?string $outcomeLabel,
        public ?string $lostReason,
        public bool $canClose,
        public bool $isCompleted,
        public bool $canComplete,
        public bool $canDelete,
        public array $tabs,
        public array $track,
        public array $queue,
        public array $people,
        public ?PaymentPlanData $paymentPlan,
        public array $galleries,
        public array $history,
    ) {}

    /** @param  bool  $queue  false where the page brings its own left pane */
    public static function fromProject(ClientCase $project, CasePositionData $position, bool $queue = true): self
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
            isBooked: $project->isBooked(),
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
            primaryShootCancelled: $project->primaryShoot?->isCancelled() ?? false,
            canManageForms: $user?->can('manageForms', $project) ?? false,
            outcomeLabel: $project->closeLabel(),
            lostReason: $project->close_reason,
            canClose: $project->lead !== null && ($user?->can('update', $project->lead) ?? false),
            isCompleted: $project->isCompleted(),
            canComplete: $project->isBooked() && ($user?->can('manageShoots', $project) ?? false),
            canDelete: ($user?->can('delete', $project) ?? false) && $project->deletionBlockers()->isEmpty(),
            // Anfrage and Aufgaben are no tabs of a project: the overview is the
            // inquiry's own page carried on, and holds the task list. Angebot,
            // Vertrag and Fragebögen are one tab, Dokumente, with the three as
            // its children.
            tabs: ! $project->isBooked() && $project->lead !== null ? self::leadTabs($project) : [
                ['key' => 'overview', 'label' => 'project.overview', 'href' => "/auftraege/{$project->public_id}", 'count' => null, 'countTo' => null, 'children' => []],
                ['key' => 'shoots', 'label' => 'project.shoots', 'href' => "/auftraege/{$project->public_id}/shootings", 'count' => null, 'countTo' => null, 'children' => []],
                ['key' => 'messages', 'label' => 'project.messages', 'href' => "/auftraege/{$project->public_id}/nachrichten", 'count' => self::waitingThreads($project), 'countTo' => null, 'children' => []],
                ['key' => 'galleries', 'label' => 'contacts.galleries', 'href' => "/auftraege/{$project->public_id}/galerien", 'count' => $project->galleries()->count(), 'countTo' => null, 'children' => []],
                self::documentsTab($project),
            ],
            track: app(ReadStageTrack::class)($position),
            queue: $user === null || ! $queue ? [] : app(OpenCases::class)->queue($user, $project),
            people: $project->contacts->map(CasePersonData::fromContact(...))->all(),
            paymentPlan: $project->paymentPlan
                ? PaymentPlanData::fromModel($project->paymentPlan->load('installments.invoice.creditNotes'))
                : null,
            galleries: $project->galleries()->get()->map(fn ($gallery) => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
            ])->all(),
            history: app(BuildThread::class)($project),
        );
    }

    /**
     * Angebot, Vertrag and Fragebögen under one tab. The tab opens on the
     * quotes and carries what each of the three still owes as one number.
     */
    private static function documentsTab(ClientCase $project): array
    {
        $base = "/auftraege/{$project->public_id}";
        $children = [
            ['key' => 'quotes', 'label' => 'project.quotes', 'translate' => true, 'href' => "{$base}/angebote", 'count' => $project->quotes()->where('status', QuoteStatus::Sent)->count(), 'reorderable' => false],
            ['key' => 'contract', 'label' => 'project.contract', 'translate' => true, 'href' => "{$base}/vertrag", 'count' => self::unsignedContracts($project), 'reorderable' => false],
            ['key' => 'forms', 'label' => 'project.forms', 'translate' => true, 'href' => "{$base}/frageboegen", 'count' => self::unansweredForms($project), 'reorderable' => false],
        ];

        return [
            'key' => 'documents',
            'label' => 'project.documents',
            'href' => $children[0]['href'],
            'count' => array_sum(array_column($children, 'count')) ?: null,
            'countTo' => null,
            'children' => $children,
        ];
    }

    /**
     * Before the booking the record is an inquiry: shootings, tasks, forms and
     * the contract have nothing to hold yet, and its screens live under
     * /anfragen.
     */
    private static function leadTabs(ClientCase $project): array
    {
        $case = "/anfragen/{$project->public_id}";

        return [
            ['key' => 'overview', 'label' => 'project.overview', 'href' => $case, 'count' => null, 'countTo' => null, 'children' => []],
            ['key' => 'quotes', 'label' => 'project.quotes', 'href' => "{$case}/angebot", 'count' => $project->quotes()->where('status', QuoteStatus::Sent)->count(), 'countTo' => null, 'children' => []],
            ['key' => 'messages', 'label' => 'project.messages', 'href' => "{$case}/nachrichten", 'count' => self::waitingThreads($project), 'countTo' => null, 'children' => []],
        ];
    }

    /**
     * The rule the inbox reads by: whoever wrote the last line owes the next
     * one. A thread the studio answered last is settled, and one with no
     * message at all has nobody owing anything.
     */
    private static function waitingThreads(ClientCase $project): int
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
    private static function unansweredForms(ClientCase $project): int
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
    private static function unsignedContracts(ClientCase $project): int
    {
        return $project->contracts()
            ->where('status', ContractStatus::Rendered)
            ->whereDoesntHave('signature')
            ->count();
    }
}
