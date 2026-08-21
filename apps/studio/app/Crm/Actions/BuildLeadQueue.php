<?php

namespace App\Crm\Actions;

use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use Illuminate\Support\Str;

/**
 * The queue beside a case, grouped by working state — unanswered, offer out,
 * accepted — not by the six funnel stages. Six stages are the view of the
 * funnel; three groups are the view of the day.
 *
 * The board reads the same grouping, so the pane and the table beside it
 * cannot disagree about which group a lead is in.
 */
class BuildLeadQueue
{
    public const GROUPS = [
        'unbeantwortet' => [LeadStage::New, LeadStage::Contacted],
        'angebot' => [LeadStage::QuoteSent],
        'angenommen' => [LeadStage::QuoteAccepted, LeadStage::ContractSent, LeadStage::Booked],
    ];

    /** @return list<array{key: string, rows: list<array<string, mixed>>}> */
    public function __invoke(?Lead $open = null): array
    {
        $leads = Lead::query()
            ->open()
            ->unbooked()
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
                    // Two lines at the pane's width. Longer runs under the
                    // next row and the group stops being scannable.
                    'snippet' => Str::limit((string) $lead->inquiry_text, 90) ?: null,
                    'open' => $open !== null && $lead->public_id === $open->public_id,
                    'waitingDays' => (int) $lead->stage_entered_at->startOfDay()->diffInDays(now()->startOfDay()),
                ])->values()->all(),
        ])->values()->all();
    }
}
