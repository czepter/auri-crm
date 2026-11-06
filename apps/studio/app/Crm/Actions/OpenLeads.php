<?php

namespace App\Crm\Actions;

use App\Crm\Data\DateConflictData;
use App\Crm\Data\LeadCardData;
use App\Crm\Data\LeadQueueRowData;
use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Stage;
use App\Crm\Support\CrmVocabulary;
use Illuminate\Support\Collection;

/**
 * Every open inquiry, read once per request, in the three shapes the screens
 * ask for: the cards a board column holds, those cards grouped by working
 * state, and the rows of the queue pane.
 *
 * Working state — unanswered, offer out, accepted — is not the six funnel
 * stages: six stages are the view of the funnel, three groups are the view of
 * the day. The grouping is private, so a screen that wants it asks here
 * instead of rebuilding it beside the pane.
 */
class OpenLeads
{
    private const GROUPS = ['unbeantwortet', 'angebot', 'angenommen'];

    /** @var Collection<int, Lead>|null */
    private ?Collection $leads = null;

    /** @var Collection<int, LeadCardData>|null */
    private ?Collection $cards = null;

    /** @var array<string, list<DateConflictData>>|null */
    private ?array $conflicts = null;

    public function __construct(
        private readonly CheckDateAvailability $availability,
        private readonly CrmVocabulary $stages,
    ) {}

    /** @return Collection<int, LeadCardData> */
    public function cards(): Collection
    {
        return $this->cards ??= $this->leads()->map(function (Lead $lead) {
            $date = $lead->case->primaryShoot?->date?->format('Y-m-d');

            // Every shoot on that date, minus this project's own — the query
            // cannot exclude it, because a different card needs that same row.
            $conflicts = collect($this->conflicts()[$date] ?? [])
                ->reject(fn ($conflict) => $conflict->projectId === $lead->case->public_id)
                ->values()
                ->all();

            return LeadCardData::fromLead($lead, $conflicts);
        })->values();
    }

    /** @return list<array{key: string, rows: list<LeadCardData>}> */
    public function groups(): array
    {
        $byLead = $this->cards()->keyBy(fn (LeadCardData $card) => $card->id);

        return $this->grouped(fn (Lead $lead) => $byLead[$lead->case->public_id]);
    }

    /** @return list<array{key: string, rows: list<LeadQueueRowData>}> */
    public function queue(?Lead $open = null): array
    {
        return $this->grouped(fn (Lead $lead) => LeadQueueRowData::fromLead($lead, $open, $this->step($lead)));
    }

    private function step(Lead $lead): string
    {
        return match (true) {
            $lead->case->primaryShoot?->date === null => 'date',
            $this->group($lead->stage) === 'angenommen' => 'book',
            $this->group($lead->stage) === 'angebot' => 'followUp',
            $lead->case->currentQuote?->status->value === 'draft' => 'sendQuote',
            default => 'reply',
        };
    }

    /** The sidebar's badge, which needs the number and none of the rows. */
    public function count(): int
    {
        return Lead::query()->open()->unbooked()->count();
    }

    /** @return list<array{key: string, rows: list<mixed>}> */
    private function grouped(callable $row): array
    {
        return collect(self::GROUPS)->map(fn (string $key) => [
            'key' => $key,
            'rows' => $this->leads()
                ->filter(fn (Lead $lead) => $this->group($lead->stage) === $key)
                // Undated last. An inquiry without a date is not the most
                // urgent one, and both views sort through this one line.
                ->sortBy(fn (Lead $lead) => $lead->case->primaryShoot?->date?->toDateString() ?? '9999-99-99')
                ->map($row)
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * Which of the three the stage falls in, read off the anchors rather than
     * a list of stage keys: a studio that inserts "Vorgespräch" before the
     * offer has added an unanswered inquiry, and nothing here should need
     * telling. A stage before the offer anchor is unanswered, one at or past
     * acceptance is accepted, the span between them is the offer.
     */
    private function group(?Stage $stage): string
    {
        // An inquiry no stage claims is still one the badge counts.
        if ($stage === null) {
            return 'unbeantwortet';
        }

        $sent = $this->stages->anchored(StagePipeline::Lead, StageAnchor::QuoteSent)?->sort_order;
        $accepted = $this->stages->anchored(StagePipeline::Lead, StageAnchor::QuoteAccepted)?->sort_order;

        return match (true) {
            $accepted !== null && $stage->sort_order >= $accepted => 'angenommen',
            $sent !== null && $stage->sort_order >= $sent => 'angebot',
            default => 'unbeantwortet',
        };
    }

    /** @return Collection<int, Lead> */
    private function leads(): Collection
    {
        return $this->leads ??= Lead::query()
            ->open()
            ->unbooked()
            ->with(['case.contacts', 'case.shoots', 'case.primaryShoot', 'case.currentQuote'])
            ->orderByDesc('stage_entered_at')
            ->get();
    }

    /**
     * One conflict query for every date on the screen. Resolving per card is
     * the obvious implementation and is an N+1 nobody notices at eleven cards.
     *
     * @return array<string, list<DateConflictData>>
     */
    private function conflicts(): array
    {
        return $this->conflicts ??= $this->availability->forDates(
            $this->leads()
                ->map(fn (Lead $lead) => $lead->case->primaryShoot?->date?->format('Y-m-d'))
                ->filter()
                ->unique()
                ->values()
                ->all()
        );
    }
}
