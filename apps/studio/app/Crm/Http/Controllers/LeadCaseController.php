<?php

namespace App\Crm\Http\Controllers;

use App\Catalog\Models\Package;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Data\InternalNoteData;
use App\Crm\Data\ShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Http\Controllers\Controller;
use App\Quotes\Data\QuoteData;
use App\Support\RecordVisit;
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
    /** Who owes the next move. Same reading the board's table groups by. */
    private const GROUPS = [
        'unbeantwortet' => [LeadStage::New, LeadStage::Contacted],
        'angebot' => [LeadStage::QuoteSent],
        'angenommen' => [LeadStage::QuoteAccepted, LeadStage::ContractSent, LeadStage::Booked],
    ];

    public function __construct(private readonly CheckDateAvailability $availability) {}

    public function show(Lead $lead): Response
    {
        $project = $lead->project;

        $this->authorize('view', $project);

        RecordVisit::record(
            $project->job()->exists() ? 'projekt' : 'anfrage',
            $project->public_id,
            $project->name,
        );

        $project->load(['contacts', 'shoots.crew.user', 'tasks.project', 'notes.author', 'latestQuote.items']);
        $primary = $project->primaryShoot;

        return Inertia::render('Leads/Show', [
            'queue' => $this->queue($lead),
            'lead' => [
                'id' => $lead->public_id,
                'projectId' => $project->public_id,
                'name' => $project->name,
                'type' => $project->type->label(),
                'source' => $lead->source->label(),
                'stage' => $lead->stage->value,
                'stageLabel' => $lead->stage->label(),
                'inquiryText' => $lead->inquiry_text,
                'capturedVia' => $lead->captured_via,
                'capturedAt' => $lead->created_at->toIso8601String(),
                'booked' => $project->job()->exists(),
            ],
            // The contact column stands beside the quote, not small underneath
            // it: the phone number is the reason the screen is open.
            'people' => $project->contacts->map(fn ($contact) => [
                'id' => $contact->public_id,
                'name' => $contact->name,
                'role' => $contact->pivot->role,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ])->all(),
            'shoots' => ShootData::collect(
                $project->shoots->map(fn (Shoot $shoot) => ShootData::fromShoot($shoot))
            ),
            'quote' => $project->latestQuote === null ? null : QuoteData::fromModel($project->latestQuote),
            // Without a quote, the active packages with their item count and
            // sum — the next move is choosing one, so the screen offers the
            // choice rather than an empty section.
            'packages' => $project->latestQuote !== null ? [] : Package::query()
                ->where('is_active', true)
                ->with('items')
                ->orderBy('name')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->public_id,
                    'name' => $package->name,
                    'itemCount' => $package->items->count(),
                    'total' => (int) $package->items->sum(fn ($item) => (int) $item->unit_price * $item->quantity),
                ])->all(),
            'tasks' => TaskRowData::collect(
                $project->tasks->sortBy('due_on')->values()->map(TaskRowData::fromTask(...))
            ),
            'notes' => InternalNoteData::collect(
                $project->notes->map(InternalNoteData::fromNote(...))
            ),
            'week' => $this->week($project, $primary?->date?->format('Y-m-d')),
            'conflicts' => $this->availability->forDate($primary?->date?->format('Y-m-d'), $project),
        ]);
    }

    /**
     * The queue itself. Every open inquiry, so ↑↓ can walk it without the
     * screen having to fetch the next one.
     */
    private function queue(Lead $open): array
    {
        $leads = Lead::query()
            ->open()
            ->with(['project.contacts', 'project.primaryShoot'])
            ->get();

        return collect(self::GROUPS)->map(fn (array $stages, string $key) => [
            'key' => $key,
            'rows' => $leads
                ->filter(fn (Lead $lead) => in_array($lead->stage, $stages, true))
                ->sortBy(fn (Lead $lead) => $lead->project->primaryShoot?->date?->toDateString() ?? '9999-99-99')
                ->map(fn (Lead $lead) => [
                    'id' => $lead->public_id,
                    'name' => $lead->project->contacts->pluck('name')->join(' & ') ?: $lead->project->name,
                    'date' => $lead->project->primaryShoot?->date?->format('Y-m-d'),
                    'open' => $lead->public_id === $open->public_id,
                    'waitingDays' => (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
                ])->values()->all(),
        ])->values()->all();
    }

    /**
     * What lies around the requested day: every other project's shoots in
     * that week. A date is answered by what is already on it, not by a rule.
     *
     * @return list<array{date: string, what: string}>
     */
    private function week(Project $project, ?string $date): array
    {
        if ($date === null) {
            return [];
        }

        $from = \Carbon\CarbonImmutable::parse($date)->subDays(3);

        return Shoot::query()
            ->with('project')
            ->whereKeyNot($project->shoots->pluck('id')->all() ?: [0])
            ->uncancelled()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $from->addDays(6)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn (Shoot $shoot) => [
                'date' => $shoot->date->format('Y-m-d'),
                'what' => $shoot->project->name,
            ])
            ->all();
    }
}
