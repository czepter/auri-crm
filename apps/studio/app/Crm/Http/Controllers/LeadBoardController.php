<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Data\LeadCardData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LeadBoardController extends Controller
{
    public function __invoke(CheckDateAvailability $availability): Response
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->open()
            ->with(['project.contacts', 'project.shoots', 'project.primaryShoot'])
            ->orderByDesc('stage_entered_at')
            ->get();

        // One conflict query for the whole board. Resolving per card is the
        // obvious implementation and is an N+1 nobody notices at eleven cards.
        $dates = $leads
            ->map(fn (Lead $lead) => $lead->project->primaryShoot?->date?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $conflictsByDate = $availability->forDates($dates);

        $cards = $leads->map(function (Lead $lead) use ($conflictsByDate) {
            $date = $lead->project->primaryShoot?->date?->format('Y-m-d');

            // Every shoot on that date, minus this project's own — the query
            // cannot exclude it, because a different card needs that same row.
            $conflicts = collect($conflictsByDate[$date] ?? [])
                ->reject(fn ($conflict) => $conflict->projectId === $lead->project->public_id)
                ->values()
                ->all();

            return LeadCardData::fromLead($lead, $conflicts);
        });

        // Grouped in PHP, in LeadStage declaration order. The funnel order is
        // not the alphabetical order of the stored strings, and encoding it in
        // SQL means a case expression kept in sync with the enum by hand.
        $columns = collect(LeadStage::cases())->map(fn (LeadStage $stage) => [
            'stage' => $stage->value,
            'label' => $stage->label(),
            'cards' => $cards->where('stage', $stage->value)->values()->all(),
        ])->all();

        return Inertia::render('Leads/Board', ['columns' => $columns]);
    }
}
