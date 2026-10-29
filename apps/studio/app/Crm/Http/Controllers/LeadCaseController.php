<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\BuildThread;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Actions\DeriveCasePosition;
use App\Crm\Actions\OpenLeads;
use App\Crm\Actions\UpdateContact;
use App\Crm\Data\CasePersonData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\CaptureChannel;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadSource;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\ProjectType;
use App\Crm\Models\Shoot;
use App\Crm\Models\Stage;
use App\Crm\Screens\CaseHeaderData;
use App\Crm\Support\CrmVocabulary;
use App\Crm\Support\OtherSuggestions;
use App\Engagement\Data\ThreadData;
use App\Http\Controllers\Controller;
use App\Quotes\Actions\ReadCaseQuote;
use App\Quotes\Screens\ReadQuoteList;
use App\Settings\Rules\ValidFormat;
use App\Support\RecordVisit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * C-08 (#243): one inquiry, beside the queue it came from.
 *
 * An inquiry and a project are the same record in two phases, so they are the
 * same screen with the sections that exist in that phase. This one is the
 * inquiry's: what they wrote, when they want it, what has been offered, what
 * is owed, what has been said.
 *
 * The queue groups by working state — unanswered, offer out, accepted — not
 * by the six funnel stages. Six stages are the view of the funnel; three
 * groups are the view of the day. And it stays on screen, because response
 * time is the lever: eight days of silence happen when every step costs a
 * page load.
 *
 * The head carries identity and nothing else. The phase line the draft
 * started with ("Neu seit heute 09:05 · kein Angebot · Termin nicht
 * bestätigt") stood in full twenty pixels further down — `angefragt` on the
 * shoot row, the packages under Angebot, the group in the queue. State
 * belongs where it is touched.
 */
class LeadCaseController extends Controller
{
    public function __construct(
        private readonly CheckDateAvailability $availability,
        private readonly OpenLeads $leads,
        private readonly BuildThread $thread,
    ) {}

    public function show(ClientCase $case, string $tab = 'overview'): Response|RedirectResponse
    {
        $this->authorize('view', $case);

        // A booked inquiry lives on as the project's overview.
        if ($case->isBooked()) {
            return redirect("/auftraege/{$case->public_id}");
        }

        $lead = $case->lead()->firstOrFail();

        RecordVisit::record(
            $case->isBooked() ? 'projekt' : 'anfrage',
            $case->public_id,
            $case->name,
        );

        $case->load(['contacts', 'shoots.crew.user', 'primaryShoot', 'tasks.case', 'tasks.assignee', 'notes.author']);
        $primary = $case->primaryShoot;
        $conversation = $case->latestThread()->with('messages.contact', 'messages.author')->first();

        return Inertia::render('Leads/Show', [
            'tab' => $tab,
            'header' => CaseHeaderData::fromCase($case, app(DeriveCasePosition::class)($case), queue: false),
            'conversation' => $conversation === null ? null : ThreadData::fromModel($conversation),
            ...($tab === 'quotes' ? app(ReadQuoteList::class)($case) : ['quotes' => [], 'payments' => null]),
            'queue' => $this->leads->queue($lead),
            'lead' => [
                'id' => $case->public_id,
                'projectId' => $case->public_id,
                'name' => $case->name,
                'type' => $case->type->key,
                'typeOther' => $case->type_other,
                'typeLabel' => $case->typeLabel(),
                'source' => $lead->source?->value,
                'sourceOther' => $lead->source_other,
                'sourceLabel' => $lead->sourceLabel(),
                'stage' => $lead->stage->key,
                'stageLabel' => $lead->stage->name,
                'inquiryText' => $lead->inquiry_text,
                'capturedVia' => $lead->captured_via === CaptureChannel::Other && $lead->captured_via_other === null
                    ? null
                    : $lead->capturedViaLabel(),
                'capturedAt' => $lead->captured_at->toIso8601String(),
                'lostReason' => $case->close_reason,
                'outcomeLabel' => $case->closeLabel(),
            ],
            // The contact column stands beside the quote, not small underneath
            // it: the phone number is the reason the screen is open.
            'people' => CasePersonData::collect(
                $case->contacts->map(CasePersonData::fromContact(...))
            ),
            'shoots' => ShootData::collect(
                $case->shoots->map(fn (Shoot $shoot) => ShootData::fromShoot($shoot))
            ),
            ...app(ReadCaseQuote::class)($case),
            'tasks' => TaskRowData::collect(
                $case->tasks->sortBy('due_on')->values()->map(TaskRowData::fromTask(...))
            ),
            'thread' => ($this->thread)($case),
            // C-10 (#245): the fixed value lists the edit state offers. Same
            // button rows the capture screen uses.
            'types' => app(CrmVocabulary::class)->types()
                ->filter(fn (ProjectType $type) => $type->is_active)
                ->map(fn (ProjectType $type) => ['value' => $type->key, 'label' => $type->name])->values()->all(),
            'sources' => collect(LeadSource::cases())
                ->map(fn (LeadSource $source) => ['value' => $source->value, 'label' => $source->label()])->all(),
            'presets' => [
                'type' => OtherSuggestions::for('type'),
                'source' => OtherSuggestions::for('source'),
            ],
            'week' => $this->week($case, $primary?->date?->format('Y-m-d')),
            'conflicts' => $this->availability->forDate($primary?->date?->format('Y-m-d'), $case),
            'stages' => app(CrmVocabulary::class)->ordered(StagePipeline::Lead)
                ->filter(fn (Stage $stage) => $stage->is_active)
                ->map(fn (Stage $stage) => ['value' => $stage->key, 'label' => $stage->name, 'anchor' => $stage->anchor?->value])->values()->all(),
        ]);
    }

    /**
     * C-10 (#245): editing is a state of this view, not a second screen and
     * not a route of its own. What it changes is the case's own fields —
     * occasion, source, the people, the inquiry text, the shoot rows.
     *
     * Quote, tasks and notes are untouched: they have their own actions and
     * their own rules. A quote is changed in the quote.
     *
     * Editability follows the phase. While it is an inquiry nothing is
     * reserved and nothing is signed, so date and place stand open. From
     * booked they are contract content and simply do not arrive here — the
     * read view keeps them, and changing them is a request (P-07), not an
     * edit. Refused rather than silently dropped: a save that reports success
     * and changes nothing is worse than one that says no.
     */
    public function update(Request $request, ClientCase $case, UpdateContact $updateContact): RedirectResponse
    {
        $lead = $case->lead()->firstOrFail();
        // The same authority that moves a shoot: the case's own fields are
        // the inquiry's substance, and ManageShoots is the capability that
        // already governs it. No new ability for the same decision.
        $this->authorize('manageShoots', $case);

        // Jede Karte des Falls schickt ihre eigene Teilmenge, seit es keinen
        // Bearbeiten-Modus über den ganzen Bildschirm mehr gibt: wer die
        // Kontakte speichert, hat den Anfragetext nicht in der Hand und darf
        // ihn nicht mitschicken müssen.
        $validated = $request->validate([
            'type' => ['sometimes', 'required', Rule::exists('project_types', 'key')->where('is_active', true)],
            'type_other' => ['nullable', 'string', 'max:120'],
            'source' => ['sometimes', 'nullable', Rule::enum(LeadSource::class)],
            'source_other' => ['nullable', 'string', 'max:120'],
            'inquiry_text' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'people' => ['array'],
            'people.*.id' => ['required', 'string', 'size:24'],
            'people.*.name' => ['required', 'string', 'max:255'],
            'people.*.email' => ['nullable', 'email', 'max:255'],
            'people.*.phone' => ['nullable', 'string', 'max:64', new ValidFormat('phone')],
            // Freitext: „Trauredner" ist eine Rolle, die in keiner Aufzählung
            // steht. CaseContactRole bleibt die Liste der Vorschläge.
            'people.*.role' => ['nullable', 'string', 'max:60'],
            'shoots' => ['array'],
            'shoots.*.id' => ['required', 'string', 'size:24'],
            'shoots.*.date' => ['nullable', 'date_format:Y-m-d'],
            'shoots.*.venue_name' => ['nullable', 'string', 'max:255'],
            'shoots.*.venue_city' => ['nullable', 'string', 'max:255'],
            'shoots.*.call_time' => ['nullable', 'date_format:H:i'],
            'shoots.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($case, $lead, $validated, $updateContact) {
            // Ein Termin verschiebt sich, auch nachdem gebucht wurde — das ist
            // der häufigste Vorgang überhaupt und war bis hierher ein 422.
            if (array_key_exists('type', $validated)) {
                $case->update([
                    'type' => $validated['type'],
                    'type_other' => $validated['type_other'] ?? null,
                ]);
            }

            $leadFields = array_intersect_key($validated, array_flip(['source', 'source_other', 'inquiry_text']));

            if ($leadFields !== []) {
                $lead->update($leadFields);
            }

            foreach ($validated['people'] ?? [] as $person) {
                $contact = $case->contacts()->where('contacts.public_id', $person['id'])->first();

                if ($contact === null) {
                    continue;
                }

                $updateContact($contact, [
                    'name' => $person['name'],
                    'email' => $person['email'] ?? null,
                    'phone' => $person['phone'] ?? null,
                ]);

                // Der Hauptkontakt behält seine Rolle: an ihr hängt der
                // partielle Unique-Index, und wer bucht, entscheidet die
                // Erfassung.
                if (filled($person['role'] ?? null) && $contact->pivot->role !== CaseContactRole::Primary->value) {
                    $case->contacts()->updateExistingPivot($contact->id, ['role' => $person['role']]);
                }
            }

            foreach ($validated['shoots'] ?? [] as $row) {
                $case->shoots()
                    ->where('public_id', $row['id'])
                    ->first()
                    ?->update([
                        'date' => $row['date'] ?? null,
                        'venue_name' => $row['venue_name'] ?? null,
                        'venue_city' => $row['venue_city'] ?? null,
                        'call_time' => $row['call_time'] ?? null,
                        'end_time' => $row['end_time'] ?? null,
                    ]);
            }
        });

        return back();
    }

    // ponytail: resolves the lead ids /anfragen carried before #520; drop once
    // notifications and bookmarks holding them have aged out.
    public static function legacyRedirect(Request $request): RedirectResponse
    {
        $legacy = $request->route()->originalParameter('case');
        $case = Lead::query()->where('legacy_public_id', $legacy)->firstOrFail()->case;

        return redirect(str_replace($legacy, $case->public_id, $request->getRequestUri()), 301);
    }

    /**
     * The queue itself. Every open inquiry, so ↑↓ can walk it without the
     * screen having to fetch the next one.
     */
    /**
     * What lies around the requested day: every other project's shoots in
     * that week. A date is answered by what is already on it, not by a rule.
     *
     * @return list<array{date: string, what: string, projectId: string}>
     */
    private function week(ClientCase $case, ?string $date): array
    {
        if ($date === null) {
            return [];
        }

        $from = CarbonImmutable::parse($date)->subDays(3);

        return Shoot::query()
            ->with('case')
            ->whereKeyNot($case->shoots->pluck('id')->all() ?: [0])
            ->uncancelled()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $from->addDays(6)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn (Shoot $shoot) => [
                'date' => $shoot->date->format('Y-m-d'),
                'what' => $shoot->case->name,
                'projectId' => $shoot->case->public_id,
            ])
            ->all();
    }
}
