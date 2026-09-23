<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\ClientCase;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadStageController extends Controller
{
    public function update(Request $request, ClientCase $case, AdvanceLeadStage $advance, CrmVocabulary $stages): RedirectResponse
    {
        $lead = $case->lead()->firstOrFail();
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'stage' => [
                'required',
                Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Lead->value)->where('is_active', true),
            ],
            // Seit wann die Anfrage dort steht — für Stufen, die erst später
            // nachgetragen werden. Leer heißt jetzt.
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $advance(
                $lead,
                $stages->byKey(StagePipeline::Lead, $validated['stage']),
                auth('staff')->user(),
                isset($validated['entered_at']) ? CarbonImmutable::parse($validated['entered_at']) : null,
            );
        } catch (DomainException $e) {
            // A rejected transition is a validation failure to the studio, not
            // a 500 — the board renders it beside the card they clicked.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return back(fallback: '/anfragen');
    }

    /**
     * T-20: schliessen, mit dem Ausgang und dem Grund. Der Ausgang steht
     * neben dem Freitext, weil `declined` — das Studio hat abgelehnt — nicht
     * in dieselbe Verlustquote gehoert wie `lost`.
     */
    public function close(Request $request, ClientCase $case, AdvanceLeadStage $advance): RedirectResponse
    {
        $lead = $case->lead()->firstOrFail();
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(LeadOutcome::class)],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $advance->markLost(
                $lead,
                $validated['reason'],
                auth('staff')->user(),
                LeadOutcome::from($validated['outcome']),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
        }

        return back(fallback: '/anfragen');
    }

    /** Das Gegenstück zu close(): eine irrtümlich geschlossene Anfrage steht wieder in ihrer Stufe. */
    public function reopen(ClientCase $case, AdvanceLeadStage $advance): RedirectResponse
    {
        $lead = $case->lead()->firstOrFail();
        $this->authorize('update', $lead);

        try {
            $advance->reopen($lead, auth('staff')->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
        }

        return back();
    }
}
