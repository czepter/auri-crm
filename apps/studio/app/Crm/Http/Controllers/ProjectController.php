<?php

namespace App\Crm\Http\Controllers;

use App\Catalog\Data\CatalogItemData;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\BookProject;
use App\Crm\Actions\BuildThread;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Actions\CrewAvailability;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Actions\ReadProjectScreen;
use App\Crm\Data\FormInstanceData;
use App\Crm\Data\ProjectGalleryData;
use App\Crm\Data\ProjectHeaderData;
use App\Crm\Data\ProjectPersonData;
use App\Crm\Data\ProjectRowData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\CrewRole;
use App\Crm\Enums\FormFieldType;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\TaskAnchors;
use App\Documents\Data\MailTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Engagement\Data\ThreadData;
use App\Http\Controllers\Controller;
use App\Payments\Data\PaymentPlanData;
use App\Quotes\Data\QuoteData;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use App\Support\RecordVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly DeriveProjectPosition $position,
        private readonly CheckDateAvailability $availability,
        private readonly StudioSettingsResolver $settings,
        private readonly BuildThread $thread,
        private readonly ReadProjectScreen $screen,
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
            // Booked only. An unbooked project is an inquiry and belongs on
            // the board at /anfragen; listing it here shows the same record
            // twice under two vocabularies.
            ->whereHas('job')
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

    public function show(Project $project, TaskAnchors $anchors): Response
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
            'shootOptions' => $this->shootOptions($project),
            'anchors' => $anchors->forProject($project),
            // Notes, the whitelisted audit trail and what got done, in one
            // order. Admin-only by omission: the portal's own ProjectController
            // does not render this key. An internal trace on a client page
            // would disclose staff names and internal event vocabulary.
            'thread' => ($this->thread)($project),
            // C-11 (#247): the key figures, each with the line that proves it.
            'figures' => $this->screen->figures($project),
            // The scope as it actually stands, not a copy of the quote. The
            // divergence is the difference between the draft at this project
            // and the accepted quote; nothing is locked, because the order
            // changes in conversation and the document lags behind — forbid
            // it and you get a scope that is wrong while the work happens
            // anyway.
            'scope' => $this->screen->scope($project),
            'people' => ProjectPersonData::collect(
                $project->contacts->map(ProjectPersonData::fromContact(...))
            ),
            // C-12 (#248): what the edit state offers as fixed choices.
            'stages' => app(CrmVocabulary::class)->ordered(StagePipeline::Lead)
                ->filter(fn (Stage $stage) => $stage->is_active)
                ->map(fn (Stage $stage) => ['value' => $stage->key, 'label' => $stage->name])->values()->all(),
            'sources' => collect(LeadSource::cases())
                ->map(fn (LeadSource $source) => ['value' => $source->value, 'label' => $source->label()])->all(),
            'roles' => collect(ProjectContactRole::cases())
                ->map(fn (ProjectContactRole $role) => ['value' => $role->value, 'label' => $role->value])->all(),
            'presets' => ['source' => $this->settings->leadPresets('source')],
            'stage' => $project->lead?->stage->key,
            'source' => $project->lead?->source->value,
            'sourceOther' => $project->lead?->source_other,
            'sourceLabel' => $project->lead?->sourceLabel(),
            'galleries' => $project->galleries()->get()->map(fn ($gallery) => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
            ])->all(),
        ]);
    }

    /**
     * C-12 (#248): editing is a state of this screen, not a second one — the
     * same answer #245 gives for the inquiry, so the two screens behave
     * alike: one save for the whole state, and the head stays put.
     *
     * What is operable: the project name, the funnel stage, the source, every
     * shoot as a block, and each person's role. What is not — next date,
     * value, what is open — carries the reason at its own label rather than a
     * disabled field: the value follows from the line items, not the other
     * way round, and without the note the operator hunts for a field that
     * must not exist.
     *
     * Creating stays independent of this state: a note, a task, a shoot each
     * have their own form at their own section. Nobody should have to unlock
     * the whole record to write one line.
     */
    public function update(Request $request, Project $project, AdvanceLeadStage $advance): RedirectResponse
    {
        $this->authorize('manageShoots', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Lead->value)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'source_other' => ['nullable', 'string', 'max:120'],
            'people' => ['array'],
            'people.*.id' => ['required', 'string', 'size:24'],
            'people.*.role' => ['required', Rule::enum(ProjectContactRole::class)],
            'shoots' => ['array'],
            'shoots.*.id' => ['required', 'string', 'size:24'],
            'shoots.*.date' => ['nullable', 'date_format:Y-m-d'],
            'shoots.*.call_time' => ['nullable', 'date_format:H:i'],
            'shoots.*.end_time' => ['nullable', 'date_format:H:i'],
            'shoots.*.venue_name' => ['nullable', 'string', 'max:255'],
            'shoots.*.venue_city' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($project, $validated, $advance) {
            $project->update(['name' => $validated['name']]);

            if ($project->lead !== null) {
                $project->lead->update(array_filter([
                    'source' => $validated['source'] ?? null,
                ]) + ['source_other' => $validated['source_other'] ?? null]);

                // Through the action, not as a column: it is the only writer
                // of the stage, and "Gebucht" set here has to book the project
                // exactly as the board's own move does.
                $stage = isset($validated['stage'])
                    ? app(CrmVocabulary::class)->byKey(StagePipeline::Lead, $validated['stage'])
                    : null;

                if ($stage !== null && ! $stage->is($project->lead->stage)) {
                    $advance($project->lead, $stage, auth('staff')->user());
                }
            }

            foreach ($validated['people'] ?? [] as $person) {
                $contact = $project->contacts()->where('contacts.public_id', $person['id'])->first();

                if ($contact !== null) {
                    $project->contacts()->updateExistingPivot($contact->id, ['role' => $person['role']]);
                }
            }

            foreach ($validated['shoots'] ?? [] as $row) {
                $project->shoots()->where('public_id', $row['id'])->first()?->update([
                    'date' => $row['date'] ?? null,
                    'call_time' => $row['call_time'] ?? null,
                    'end_time' => $row['end_time'] ?? null,
                    'venue_name' => $row['venue_name'] ?? null,
                    'venue_city' => $row['venue_city'] ?? null,
                ]);
            }
        });

        return back();
    }

    public function shoots(Project $project, CrewAvailability $crew): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Shoots', [
            'header' => $this->header($project),
            'shoots' => ShootData::collect($this->shootsOf($project)),
            // Wer an dem Tag kann, nicht nur wer existiert: frei, abwesend
            // oder auf einem anderen Shooting.
            'crewAvailability' => $crew->forProject($project),
            'crewRoles' => collect(CrewRole::cases())
                ->map(fn (CrewRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->all(),
        ]);
    }

    /**
     * Die Aufgaben eines Projekts als eigener Bereich: auf der Übersicht steht
     * nur, was offen ist, hier auch das Erledigte und wonach gefiltert wird.
     */
    public function tasks(Project $project, TaskAnchors $anchors): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Tasks', [
            'header' => $this->header($project),
            'tasks' => TaskRowData::collect(
                $project->tasks()
                    ->with(['project', 'shoot'])
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->get()
                    ->map(TaskRowData::fromTask(...))
            ),
            'shootOptions' => $this->shootOptions($project),
            'anchors' => $anchors->forProject($project),
        ]);
    }

    /**
     * Die Galerien am Projekt. /galerien führt die des Studios; hier stehen
     * die dieses Auftrags neben den Shootings, die noch keine haben.
     */
    public function galleries(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Galleries', [
            'header' => $this->header($project),
            'galleries' => ProjectGalleryData::collect(
                $project->galleries()->with(['shoot', 'currentVersion'])->get()
            ),
            'shoots' => ShootData::collect($this->shootsOf($project)),
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    private function shootOptions(Project $project): array
    {
        return $project->shoots()->uncancelled()->get()
            ->map(fn (Shoot $shoot) => [
                'value' => $shoot->public_id,
                'label' => $shoot->label ?? $shoot->kind->label(),
            ])
            ->all();
    }

    public function quotes(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Quotes', [
            'header' => $this->header($project),
            'quotes' => QuoteData::collect(
                $project->quotes()->with(['items', 'predecessor'])->get()
            ),
            'paymentPlan' => $project->job?->paymentPlan
                ? PaymentPlanData::fromModel($project->job->paymentPlan->load('installments'))
                : null,
            // Beide Listen als Prop statt als Suchendpunkt: ein Studio führt
            // zweistellige Mengen, gefiltert wird in der Suchzeile selbst.
            'packages' => PackageData::collect(Package::active()->with('items')->orderBy('name')->get()),
            'catalogItems' => CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get()),
            'canManageCatalog' => $request->user('staff')?->can('create', Package::class) ?? false,
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
