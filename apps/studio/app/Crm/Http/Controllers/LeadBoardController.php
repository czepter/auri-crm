<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\OpenLeads;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * C-07 (#241): two views on the same cases.
 *
 * A board shows where something stands; a table shows what is next. So the
 * table groups by working state rather than by the six funnel stages — the
 * stage is a column in it, not its skeleton.
 *
 * Which view is showing follows one order of precedence: `?ansicht=` wins
 * when it is there (shareable, and the back button stays honest), otherwise
 * the one this user last chose, otherwise the board. The sidebar's own link
 * stays parameterless.
 */
class LeadBoardController extends Controller
{
    public function __invoke(Request $request, OpenLeads $leads): Response
    {
        $this->authorize('viewAny', Lead::class);

        $cards = $leads->cards();

        // Grouped in PHP, in LeadStage declaration order. The funnel order is
        // not the alphabetical order of the stored strings, and encoding it in
        // SQL means a case expression kept in sync with the enum by hand.
        // The column head carries the count and the sum — the question staff
        // otherwise go to Auswertungen for.
        $columns = collect(LeadStage::cases())->map(function (LeadStage $stage) use ($cards) {
            $inStage = $cards->where('stage', $stage->value)->values();

            return [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'cards' => $inStage->all(),
                'total' => (int) $inStage->sum('value'),
            ];
        })->all();

        return Inertia::render('Leads/Board', [
            'columns' => $columns,
            'groups' => $leads->groups(),
            'queue' => $leads->queue(),
            'view' => $this->view($request),
        ]);
    }

    /**
     * The chosen view, kept on the user so it survives a second machine and a
     * reinstall. A cookie would not, and the whole point of the setting is
     * that nobody has to make it twice.
     */
    public function chooseView(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ansicht' => ['required', 'in:board,liste']]);

        auth('staff')->user()->update(['lead_view' => $validated['ansicht']]);

        return back();
    }

    private function view(Request $request): string
    {
        $asked = $request->query('ansicht');

        if (in_array($asked, ['board', 'liste'], true)) {
            return $asked;
        }

        return auth('staff')->user()->lead_view ?? 'board';
    }
}
