<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\BookCase;
use App\Crm\Actions\BuildThread;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Actions\CompleteCase;
use App\Crm\Actions\CrewAvailability;
use App\Crm\Actions\DeriveCasePosition;
use App\Crm\Actions\WorkflowColumns;
use App\Crm\Actions\WriteProjectFieldValues;
use App\Crm\Data\CasePersonData;
use App\Crm\Data\CaseRowData;
use App\Crm\Data\FormInstanceData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\FormFieldType;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Models\FormTemplate;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Models\TaskTemplate;
use App\Crm\Screens\CaseGalleryData;
use App\Crm\Screens\CaseHeaderData;
use App\Crm\Screens\ReadCaseScreen;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\OtherSuggestions;
use App\Crm\Support\StageSettingsPayload;
use App\Crm\Support\TaskAnchors;
use App\Documents\Data\MailTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeData;
use App\Engagement\Data\ThreadData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Quotes\Actions\ReadCaseQuote;
use App\Quotes\Screens\ReadQuoteList;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use App\Support\RecordVisit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function __construct(
        private readonly DeriveCasePosition $position,
        private readonly CheckDateAvailability $availability,
        private readonly BuildThread $thread,
        private readonly ReadCaseScreen $screen,
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
        $this->authorize('viewAny', ClientCase::class);

        $projects = ClientCase::query()
            ->visibleTo(auth('staff')->user())
            // Booked only. An unbooked project is an inquiry and belongs on
            // the board at /anfragen; listing it here shows the same record
            // twice under two vocabularies.
            ->booked()
            ->with(['primaryShoot', 'lead', 'shoots', 'contacts', 'latestQuote'])
            // What the workflow produced, as the list's own column: a workflow
            // nobody can see the effect of is a settings screen again.
            ->withCount(['tasks', 'tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at')])
            ->get()
            ->sortBy(fn (ClientCase $case) => $case->primaryShoot?->date?->toDateString() ?? '9999-99-99')
            ->values();

        $vocabulary = app(CrmVocabulary::class);
        $booked = $vocabulary->anchored(StagePipeline::Project, StageAnchor::Booked)?->sort_order ?? 0;
        $delivered = $vocabulary->anchored(StagePipeline::Project, StageAnchor::Delivered)?->sort_order ?? PHP_INT_MAX;

        return Inertia::render('Projects/Index', [
            'view' => request()->query('ansicht') === 'board' ? 'board' : 'liste',
            'projects' => CaseRowData::collect(
                $projects->map(fn (ClientCase $case) => CaseRowData::fromCase($case, $this->position))
            ),
            // #364: the filter pills and the board's columns, in the studio's
            // own order and names — from the booking to the delivery, since
            // before it a project is an inquiry and after it an archive.
            'stages' => $vocabulary->ordered(StagePipeline::Project)
                ->filter(fn (Stage $stage) => $stage->is_active && $stage->sort_order >= $booked && $stage->sort_order <= $delivered)
                ->map(fn (Stage $stage) => ['key' => $stage->key, 'name' => $stage->name, 'phase' => $vocabulary->phase($stage)])
                ->values(),
            'stageSettings' => app(StageSettingsPayload::class)(),
            'settingsSelection' => app(StageSettingsPayload::class)->selection(request()),
            'returnTo' => app(StageSettingsPayload::class)->returnTo(request()),
            'workflow' => Inertia::optional(function () {
                if (! request()->user('staff')->can('viewAny', TaskTemplate::class)) {
                    return null;
                }

                $vocabulary = app(CrmVocabulary::class);
                $type = $vocabulary->type((string) request()->query('workflow_typ'))
                    ?? $vocabulary->types()->first();

                return app(WorkflowColumns::class)($type, StagePipeline::Project);
            }),
        ]);
    }

    public function show(ClientCase $case, TaskAnchors $anchors): Response|RedirectResponse
    {
        $this->authorize('view', $case);

        // Same rule as index(): an unbooked project is an inquiry, and its
        // screen is the case. Every /auftraege link lands right without
        // knowing the phase.
        if ($case->lead !== null && ! $case->isBooked()) {
            return redirect("/anfragen/{$case->public_id}");
        }

        // C-03 (#237): an inquiry and a project are the same record in two
        // phases, so the palette names it by the phase it is in.
        RecordVisit::record(
            $case->isBooked() ? 'projekt' : 'anfrage',
            $case->public_id,
            $case->name,
        );

        return Inertia::render('Projects/Show', [
            'header' => $this->header($case),
            'shoots' => ShootData::collect($this->shootsOf($case)),
            'shootKinds' => Shoot::kindSuggestions(),
            // Open first and by due date, then the done ones: a card whose top
            // row is something already finished buries the work.
            'tasks' => TaskRowData::collect(
                $case->tasks()
                    ->with(['case', 'shoot', 'assignee'])
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->get()
                    ->map(TaskRowData::fromTask(...))
            ),
            'shootOptions' => $this->shootOptions($case),
            'staffOptions' => $this->staffOptions(),
            'anchors' => $anchors->forCase($case),
            // Notes, the whitelisted audit trail and what got done, in one
            // order. Admin-only by omission: the portal's own CaseController
            // does not render this key. An internal trace on a client page
            // would disclose staff names and internal event vocabulary.
            'thread' => ($this->thread)($case),
            // The scope as it actually stands, not a copy of the quote. The
            // divergence is the difference between the draft at this project
            // and the accepted quote; nothing is locked, because the order
            // changes in conversation and the document lags behind — forbid
            // it and you get a scope that is wrong while the work happens
            // anyway.
            'scope' => $this->screen->scope($case),
            ...app(ReadCaseQuote::class)($case),
            'people' => CasePersonData::collect(
                $case->contacts->map(CasePersonData::fromContact(...))
            ),
            // C-25: what this type asks of a project, and what this project
            // answers. Definitions and values apart, because a value under a
            // key the type no longer defines is kept but not shown.
            'fields' => $case->type?->fields()->active()->get()
                ->map(StageSettingsPayload::field(...))->values()->all() ?? [],
            'fieldValues' => (object) ($case->field_values ?? []),
            // C-12 (#248): what the edit state offers as fixed choices. A
            // booked project stands where its shoots stand, so its Stand
            // offers their statuses.
            'stages' => app(CrmVocabulary::class)->ordered($case->isBooked() ? StagePipeline::Project : StagePipeline::Lead)
                ->filter(fn (Stage $stage) => $stage->is_active)
                ->map(fn (Stage $stage) => ['value' => $stage->key, 'label' => $stage->name])->values()->all(),
            'sources' => collect(LeadSource::cases())
                ->map(fn (LeadSource $source) => ['value' => $source->value, 'label' => $source->label()])->all(),
            'roles' => collect(CaseContactRole::cases())
                ->map(fn (CaseContactRole $role) => ['value' => $role->value, 'label' => $role->value])->all(),
            'presets' => ['source' => OtherSuggestions::for('source')],
            'stage' => $case->isBooked()
                ? $this->position->leastAdvanced($case->shoots)?->status->key
                : $case->lead?->stage->key,
            'source' => $case->lead?->source?->value,
            'sourceOther' => $case->lead?->source_other,
            'sourceLabel' => $case->lead?->sourceLabel(),
            'inquiry' => $case->lead === null ? null : [
                'text' => $case->lead->inquiry_text,
                'typeLabel' => $case->typeLabel(),
                'sourceLabel' => $case->lead->sourceLabel(),
                'capturedVia' => $case->lead->capturedViaLabel(),
                'capturedAt' => $case->lead->captured_at->toIso8601String(),
            ],
            'stalled' => $this->stalled($case),
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
    public function update(
        Request $request,
        ClientCase $case,
        AdvanceLeadStage $advance,
        WriteProjectFieldValues $writeFields,
    ): RedirectResponse {
        $this->authorize('manageShoots', $case);

        // Jede Karte des Bildschirms schickt ihre eigene Teilmenge, seit es
        // keinen Bearbeiten-Modus über den ganzen Bildschirm mehr gibt: wer die
        // Termine speichert, hat den Projektnamen nicht in der Hand.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'stage' => ['nullable', Rule::exists('stages', 'key')->where('pipeline', ($case->isBooked() ? StagePipeline::Project : StagePipeline::Lead)->value)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'source_other' => ['nullable', 'string', 'max:120'],
            'people' => ['array'],
            'people.*.id' => ['required', 'string', 'size:24'],
            // Freitext, wie am Fall: „Trauredner" steht in keiner Aufzählung.
            'people.*.role' => ['required', 'string', 'max:60'],
            'shoots' => ['array'],
            'shoots.*.id' => ['required', 'string', 'size:24'],
            'shoots.*.date' => ['nullable', 'date_format:Y-m-d'],
            'shoots.*.call_time' => ['nullable', 'date_format:H:i'],
            'shoots.*.end_time' => ['nullable', 'date_format:H:i'],
            'shoots.*.venue_name' => ['nullable', 'string', 'max:255'],
            'shoots.*.venue_city' => ['nullable', 'string', 'max:255'],
            'fields' => ['array'],
        ]);

        DB::transaction(function () use ($case, $validated, $advance, $writeFields) {
            if (array_key_exists('name', $validated)) {
                $case->update(['name' => $validated['name']]);
            }

            if ($case->lead !== null) {
                $case->lead->update(array_filter([
                    'source' => $validated['source'] ?? null,
                ]) + ['source_other' => $validated['source_other'] ?? null]);

                // Through the action, not as a column: it is the only writer
                // of the stage, and "Gebucht" set here has to book the project
                // exactly as the board's own move does.
                $stage = isset($validated['stage']) && ! $case->isBooked()
                    ? app(CrmVocabulary::class)->byKey(StagePipeline::Lead, $validated['stage'])
                    : null;

                if ($stage !== null && ! $stage->is($case->lead->stage)) {
                    $advance($case->lead, $stage, auth('staff')->user());
                }
            }

            // Fresh: the lead move above may just have booked it on another instance.
            if (isset($validated['stage']) && $case->fresh()->isBooked()) {
                $this->moveShoots($case, $validated['stage']);
            }

            foreach ($validated['people'] ?? [] as $person) {
                $contact = $case->contacts()->where('contacts.public_id', $person['id'])->first();

                // Der Hauptkontakt behält seine Rolle: an ihr hängt der
                // partielle Unique-Index auf case_contacts.
                if ($contact !== null && $contact->pivot->role !== CaseContactRole::Primary->value) {
                    $case->contacts()->updateExistingPivot($contact->id, ['role' => $person['role']]);
                }
            }

            foreach ($validated['shoots'] ?? [] as $row) {
                $case->shoots()->where('public_id', $row['id'])->first()?->update([
                    'date' => $row['date'] ?? null,
                    'call_time' => $row['call_time'] ?? null,
                    'end_time' => $row['end_time'] ?? null,
                    'venue_name' => $row['venue_name'] ?? null,
                    'venue_city' => $row['venue_city'] ?? null,
                ]);
            }

            // Through the action, not the column: the api surface and the MCP
            // server write the same values against the same definitions.
            $writeFields($case, $validated['fields'] ?? [], auth('staff')->user());
        });

        return back();
    }

    public function stage(Request $request, ClientCase $case): RedirectResponse
    {
        $this->authorize('manageShoots', $case);
        abort_unless($case->isBooked(), 404);

        $validated = $request->validate([
            'stage' => ['required', Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Project->value)->where('is_active', true)],
        ]);

        try {
            $this->moveShoots($case, $validated['stage']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return back(fallback: '/projekte');
    }

    /**
     * A booked project's Stand is its least-advanced shoot, so setting it
     * moves every open shoot. Only when it changed: the details card sends the
     * Stand along with every save, and resending the same value would drag an
     * ahead shoot back to it.
     */
    private function moveShoots(ClientCase $case, string $key): void
    {
        $shoots = $case->shoots()->uncancelled()->get();

        if ($key === $this->position->leastAdvanced($shoots)?->status->key) {
            return;
        }

        $status = app(CrmVocabulary::class)->byKey(StagePipeline::Project, $key);

        $shoots->reject(fn (Shoot $shoot) => $shoot->status->is($status))
            ->each(fn (Shoot $shoot) => app(AdvanceShootStatus::class)($shoot, $status, auth('staff')->user()));
    }

    public function shoots(ClientCase $case, CrewAvailability $crew): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Shoots', [
            'header' => $this->header($case),
            'shoots' => ShootData::collect($this->shootsOf($case)),
            // Wer an dem Tag kann, nicht nur wer existiert: frei, abwesend
            // oder auf einem anderen Shooting.
            'crewAvailability' => $crew->forCase($case),
        ]);
    }

    /**
     * Die Aufgaben eines Projekts als eigener Bereich: auf der Übersicht steht
     * nur, was offen ist, hier auch das Erledigte und wonach gefiltert wird.
     */
    public function tasks(ClientCase $case, TaskAnchors $anchors): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Tasks', [
            'header' => $this->header($case),
            'tasks' => TaskRowData::collect(
                $case->tasks()
                    ->with(['case', 'shoot', 'assignee'])
                    ->orderByRaw('completed_at is not null')
                    ->byDueDate()
                    ->get()
                    ->map(TaskRowData::fromTask(...))
            ),
            'shootOptions' => $this->shootOptions($case),
            'staffOptions' => $this->staffOptions(),
            'anchors' => $anchors->forCase($case),
        ]);
    }

    public function history(ClientCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/History', [
            'header' => $this->header($case),
            'thread' => ($this->thread)($case),
        ]);
    }

    public function notes(ClientCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Notes', [
            'header' => $this->header($case),
            'thread' => ($this->thread)($case),
        ]);
    }

    /**
     * Die Galerien am Projekt. /galerien führt die des Studios; hier stehen
     * die dieses Auftrags neben den Shootings, die noch keine haben.
     */
    public function galleries(ClientCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Galleries', [
            'header' => $this->header($case),
            'galleries' => CaseGalleryData::collect(
                $case->galleries()->with(['shoot', 'currentVersion'])->get()
            ),
            'shoots' => ShootData::collect($this->shootsOf($case)),
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    /** T-24: wer eine Aufgabe übernehmen kann. Das ganze Studio, nicht die Crew — zugewiesen wird auch quer. */
    private function staffOptions(): array
    {
        return User::query()->orderBy('name')->get()
            ->map(fn (User $user) => ['value' => $user->public_id, 'label' => $user->name])
            ->all();
    }

    private function shootOptions(ClientCase $case): array
    {
        return $case->shoots()->uncancelled()->get()
            ->map(fn (Shoot $shoot) => [
                'value' => $shoot->public_id,
                'label' => $shoot->label ?? $shoot->kind,
            ])
            ->all();
    }

    public function quotes(Request $request, ClientCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Quotes', [
            'header' => $this->header($case),
            ...app(ReadQuoteList::class)($case),
        ]);
    }

    public function forms(ClientCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('Projects/Forms', [
            'header' => $this->header($case),
            'formInstances' => FormInstanceData::collect(
                $case->formInstances()->with('fields.currentAnswer')->get()
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
        ClientCase $case,
        DocumentMergeData $mergeData,
    ): Response {
        $this->authorize('view', $case);

        $thread = $case->latestThread()->with('messages.contact', 'messages.author')->first();

        return Inertia::render('Projects/Messages', [
            'header' => $this->header($case),
            'thread' => $thread === null ? null : ThreadData::fromModel($thread),
            // E-10: T-12's mail templates, resolved against this project.
            // Sent with the screen rather than fetched on selection — there
            // are a handful of them, they share one merge-data build, and a
            // round trip per pick would buy nothing.
            'mailTemplates' => DocumentTemplate::query()
                ->current()
                ->ofType(DocumentTemplateType::Mail)
                ->forShootKind($case->primaryShoot?->kind)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentTemplate $template) => MailTemplateData::fromModel($template, $mergeData->forCase($case)))
                ->all(),
        ]);
    }

    public function book(ClientCase $case, BookCase $book): RedirectResponse
    {
        $this->authorize('book', $case);

        // Already booked is a no-op inside the action rather than a failure
        // here: two staff members pressing the button is not an error, it is a
        // race, and the second press should leave the screen consistent.
        $book($case, auth('staff')->user());

        return back();
    }

    public function complete(ClientCase $case, CompleteCase $complete): RedirectResponse
    {
        $this->authorize('manageShoots', $case);

        try {
            $complete($case, auth('staff')->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return back();
    }

    public function reopen(ClientCase $case, CompleteCase $complete): RedirectResponse
    {
        $this->authorize('manageShoots', $case);

        try {
            $complete->reopen($case, auth('staff')->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(ClientCase $case): RedirectResponse
    {
        $this->authorize('delete', $case);

        if ($case->deletionBlockers()->isNotEmpty()) {
            throw ValidationException::withMessages(['project' => 'Projekt trägt Auftrag, Angebot, Vertrag oder Galerie. Schließen statt löschen.']);
        }

        $case->delete();

        LiveUpdate::publish();

        return redirect('/projekte');
    }

    /**
     * Hide or show one project in the portal. Scoped to this project alone —
     * every contact on it, and every other project any of them is on, is
     * untouched (A-04). Revoking a contact's access entirely is a separate
     * action on ContactController, on purpose.
     */
    public function togglePortalVisibility(ClientCase $case): RedirectResponse
    {
        $this->authorize('manageVisibility', $case);

        $case->update(['portal_visible' => ! $case->portal_visible]);

        AuditLogger::record(
            event: $case->portal_visible ? 'project.portal_shown' : 'project.portal_hidden',
            actor: auth('staff')->user(),
            subject: $case->public_id,
            projectId: $case->id,
        );

        return back();
    }

    /**
     * The shooting day that passed without its status moving, and the step
     * that closes it. The queue lists the case; the overview offers the step.
     */
    private function stalled(ClientCase $case): ?array
    {
        $shoot = $case->isBooked() ? $this->position->stalled($case->shoots()->get()) : null;
        $shot = app(CrmVocabulary::class)->anchored(StagePipeline::Project, StageAnchor::Shot);

        return $shoot === null || $shot === null ? null : [
            'shootId' => $shoot->public_id,
            'label' => $shoot->label ?? $case->name,
            'date' => $shoot->date->format('Y-m-d'),
            'days' => (int) $shoot->date->copy()->startOfDay()->diffInDays(today()),
            'status' => $shoot->status->name,
            'since' => $shoot->status_entered_at?->format('Y-m-d'),
            'next' => ['key' => $shot->key, 'label' => $shot->name],
        ];
    }

    private function header(ClientCase $case): CaseHeaderData
    {
        return CaseHeaderData::fromCase(
            $case->load(['contacts', 'shoots', 'primaryShoot']),
            ($this->position)($case),
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
    private function shootsOf(ClientCase $case): array
    {
        $shoots = $case->shoots()
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
