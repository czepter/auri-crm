<?php

namespace App\Crm\Http\Controllers;

use App\Catalog\Models\Package;
use App\Crm\Actions\BookProject;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\FormInstanceData;
use App\Crm\Data\InternalNoteData;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Data\ProjectRowData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\CrewRole;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\ProjectActivity;
use App\Data\StaffProfileData;
use App\Documents\Data\MailTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Engagement\Data\ThreadData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use App\Support\RecordVisit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly DeriveProjectPosition $position,
        private readonly CheckDateAvailability $availability,
    ) {}

    /**
     * C-02 (#236): the destination the sidebar's new Projekte entry needs.
     * Projects/* owns most of the product's screens and had no list of its
     * own — a project was reachable only through the board it came off, or by
     * URL.
     *
     * Ordered by the primary shoot, nearest first, with the undated last:
     * a studio looks a project up by its day.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->visibleTo(auth('staff')->user())
            ->with(['primaryShoot', 'lead', 'job', 'shoots', 'contacts'])
            ->get()
            ->sortBy(fn (Project $project) => $project->primaryShoot?->date?->toDateString() ?? '9999-99-99')
            ->values();

        return Inertia::render('Projects/Index', [
            'projects' => ProjectRowData::collect(
                $projects->map(fn (Project $project) => ProjectRowData::fromProject($project, $this->position))
            ),
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        // C-03 (#237): an inquiry and a project are the same record in two
        // phases, so the palette names it by the phase it is in.
        RecordVisit::record(
            $project->job()->exists() ? 'projekt' : 'anfrage',
            $project->public_id,
            $project->name,
        );

        return Inertia::render('Projects/Show', [
            'header' => $this->header($project),
            'shoots' => ShootData::collect($this->shootsOf($project)),
            // Absent until CreatePaymentPlan writes one — "a project without
            // a Job has no plan" (M-03), and neither does a booked Job before
            // anyone has created its plan.
            'paymentPlan' => $project->job?->paymentPlan
                ? PaymentPlanData::fromModel($project->job->paymentPlan->load('installments'))
                : null,
            'notes' => InternalNoteData::collect(
                $project->notes()->with('author')->get()->map(InternalNoteData::fromNote(...))
            ),
            // Open first and by due date, then the done ones: a card whose top
            // row is something already finished buries the work.
            'tasks' => TaskRowData::collect(
                $project->tasks()
                    ->with(['project', 'shoot'])
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->get()
                    ->map(TaskRowData::fromTask(...))
            ),
            'shootOptions' => $project->shoots()->uncancelled()->get()
                ->map(fn ($shoot) => [
                    'value' => $shoot->public_id,
                    'label' => $shoot->label ?? $shoot->kind->label(),
                ])
                ->all(),
            // Whitelisted by ProjectActivity, admin-only by omission: the
            // portal's own ProjectController does not render this key. An
            // internal audit trail on a client page would disclose staff
            // names and internal event vocabulary to the client.
            'activity' => ProjectActivity::for($project),
            // C-11 (#247): the key figures, each with the line that proves it.
            'figures' => $this->figures($project),
            // The scope as it actually stands, not a copy of the quote. The
            // divergence is the difference between the draft at this project
            // and the accepted quote; nothing is locked, because the order
            // changes in conversation and the document lags behind — forbid
            // it and you get a scope that is wrong while the work happens
            // anyway.
            'scope' => $this->scope($project),
            'people' => $project->contacts->map(fn ($contact) => [
                'id' => $contact->public_id,
                'name' => $contact->name,
                'role' => $contact->pivot->role,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])->all(),
            'galleries' => $project->galleries()->get()->map(fn ($gallery) => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
            ])->all(),
        ]);
    }

    /**
     * The band across the top: phase, next date, value, what is open, source,
     * portal — each with one line of proof under it.
     *
     * Two fields the draft asks for are deliberately not invented. `Phase` as
     * a maintained value does not exist — it is derived (DeriveProjectPosition)
     * and stays derived. And the follow-up date is a task with a due date, so
     * it needs no column of its own: the row in "Offen" is the whole feature.
     *
     * @return array<string, array{value: ?string, proof: ?string}>
     */
    private function figures(Project $project): array
    {
        $accepted = $project->quotes()->where('status', QuoteStatus::Accepted)->latest('id')->first();
        $plan = $project->job?->paymentPlan;
        $open = $plan === null ? null : (int) $plan->installments()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->sum('amount');

        return [
            'phase' => [
                'value' => ($this->position)($project)->label,
                'proof' => $project->lead?->source->label(),
            ],
            'nextDate' => [
                'value' => $project->shoots()->uncancelled()->whereNotNull('date')
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')->value('date')?->format('Y-m-d'),
                'proof' => null,
            ],
            'value' => [
                'value' => $accepted === null ? null : (string) $accepted->total,
                'proof' => $accepted?->number,
            ],
            'open' => [
                'value' => $open === null ? null : (string) $open,
                'proof' => $plan === null ? null : (string) $plan->installments()->count(),
            ],
            'portal' => [
                'value' => $project->portal_visible ? 'on' : 'off',
                'proof' => null,
            ],
        ];
    }

    /**
     * What was ordered against what stands now.
     *
     * The accepted quote is untouched; the current state is a draft quote at
     * the same project. Every line that differs is marked, the old value
     * stays under the new one, and the strip states the difference in money.
     *
     * @return array{items: list<array<string, mixed>>, delta: int, hasDraft: bool}
     */
    private function scope(Project $project): array
    {
        $accepted = $project->quotes()->where('status', QuoteStatus::Accepted)->latest('id')->first();
        $draft = $project->quotes()->where('status', QuoteStatus::Draft)->latest('id')->first();
        $current = $draft ?? $accepted;

        if ($current === null) {
            return ['items' => [], 'delta' => 0, 'hasDraft' => false];
        }

        $before = $accepted?->items->keyBy('title') ?? collect();

        return [
            'items' => $current->items->map(function ($item) use ($before, $draft) {
                $was = $before->get($item->title);

                return [
                    'id' => $item->public_id,
                    'title' => $item->title,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    // Only a draft can diverge: without one, what stands is
                    // what was ordered.
                    'state' => $draft === null || $was !== null && (int) $was->unit_price === (int) $item->unit_price
                        ? null
                        : ($was === null ? 'new' : 'changed'),
                    'wasUnitPrice' => $was?->unit_price,
                ];
            })->all(),
            'delta' => $accepted === null || $draft === null ? 0 : (int) $draft->total - (int) $accepted->total,
            'hasDraft' => $draft !== null,
        ];
    }

    public function shoots(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Shoots', [
            'header' => $this->header($project),
            'shoots' => ShootData::collect($this->shootsOf($project)),
            // Who can be put on a shooting day, and in what capacity. Both
            // lists are the studio's, not one shoot's.
            'staff' => StaffProfileData::collect(User::query()->orderBy('name')->get()),
            'crewRoles' => collect(CrewRole::cases())
                ->map(fn (CrewRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    public function quotes(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Quotes', [
            'header' => $this->header($project),
            'quotes' => QuoteData::collect(
                $project->quotes()->with(['items', 'predecessor'])->get()
            ),
            'packages' => Package::active()->orderBy('name')->get()
                ->map(fn (Package $package) => ['value' => $package->public_id, 'label' => $package->name])
                ->all(),
        ]);
    }

    public function forms(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Forms', [
            'header' => $this->header($project),
            'formInstances' => FormInstanceData::collect(
                $project->formInstances()->with('fields.currentAnswer')->get()
            ),
            'formTemplates' => FormTemplate::active()->orderBy('name')->get()
                ->map(fn (FormTemplate $template) => ['value' => $template->public_id, 'label' => $template->name])
                ->all(),
            'fieldTypes' => collect(FormFieldType::cases())
                ->map(fn (FormFieldType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    /**
     * E-06's Kommunikation tab. Always the project's latest thread — there is
     * no screen yet that lists more than one, and no admin action starts a
     * new one; only the client does, from the portal (StartThread).
     */
    public function messages(
        Project $project,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
    ): Response {
        $this->authorize('view', $project);

        $thread = $project->latestThread()->with('messages.contact', 'messages.author')->first();

        return Inertia::render('Projects/Messages', [
            'header' => $this->header($project),
            'thread' => $thread === null ? null : ThreadData::fromModel($thread),
            // E-10: T-12's mail templates, resolved against this project.
            // Sent with the screen rather than fetched on selection — there
            // are a handful of them, they share one merge-data build, and a
            // round trip per pick would buy nothing.
            'mailTemplates' => DocumentTemplate::query()
                ->current()
                ->ofType(DocumentTemplateType::Mail)
                ->forShootKind($project->primaryShoot?->kind?->value)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentTemplate $template) => MailTemplateData::fromModel($template, $project, $settings, $mergeData))
                ->all(),
        ]);
    }

    public function book(Project $project, BookProject $book): RedirectResponse
    {
        $this->authorize('book', $project);

        // Already booked is a no-op inside the action rather than a failure
        // here: two staff members pressing the button is not an error, it is a
        // race, and the second press should leave the screen consistent.
        $book($project, auth('staff')->user());

        return back();
    }

    /**
     * Hide or show one project in the portal. Scoped to this project alone —
     * every contact on it, and every other project any of them is on, is
     * untouched (A-04). Revoking a contact's access entirely is a separate
     * action on ContactController, on purpose.
     */
    public function togglePortalVisibility(Project $project): RedirectResponse
    {
        $this->authorize('manageVisibility', $project);

        $project->update(['portal_visible' => ! $project->portal_visible]);

        AuditLogger::record(
            event: $project->portal_visible ? 'project.portal_shown' : 'project.portal_hidden',
            actor: auth('staff')->user(),
            subject: $project->public_id,
            projectId: $project->id,
        );

        return back();
    }

    private function header(Project $project): ProjectHeaderData
    {
        return ProjectHeaderData::fromProject(
            $project->load(['contacts', 'shoots', 'primaryShoot', 'job']),
            ($this->position)($project),
        );
    }

    /**
     * Chronological, undated last. sort_order breaks the tie for two shoots on
     * one day — the ceremony before the reception, whatever the studio decided.
     *
     * T-10: one CheckDateAvailability::forDates() call for every shoot's date
     * at once — the same "one query for the whole screen" shape LeadBoardController
     * already uses — narrowed per shoot to the absence conflicts naming
     * someone actually crewed on it, which is what makes this S-02's warning
     * rather than a studio-wide notice repeated on every shoot.
     */
    private function shootsOf(Project $project): array
    {
        $shoots = $project->shoots()
            ->with(['crew.user', 'timeline'])
            ->reorder()
            ->orderByRaw('date is null, date')
            ->orderBy('sort_order')
            ->get();

        $dates = $shoots->pluck('date')->filter()->map(fn ($date) => $date->format('Y-m-d'))
            ->unique()->values()->all();
        $conflictsByDate = $this->availability->forDates($dates);

        return $shoots->map(function (Shoot $shoot) use ($conflictsByDate) {
            $date = $shoot->date?->format('Y-m-d');
            $crewIds = $shoot->crew->pluck('user.public_id')->all();

            $conflicts = collect($conflictsByDate[$date] ?? [])
                ->filter(fn ($conflict) => $conflict->source === 'absence' && in_array($conflict->personId, $crewIds, true))
                ->values()
                ->all();

            return ShootData::fromShoot($shoot, $conflicts);
        })->all();
    }
}
