<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadStageController extends Controller
{
    public function update(Request $request, Lead $lead, AdvanceLeadStage $advance, CrmVocabulary $stages): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'stage' => [
                'required',
                Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Lead->value)->where('is_active', true),
            ],
        ]);

        try {
            $advance($lead, $stages->byKey(StagePipeline::Lead, $validated['stage']), auth('staff')->user());
        } catch (DomainException $e) {
            // A rejected transition is a validation failure to the studio, not
            // a 500 — the board renders it beside the card they clicked.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return redirect('/anfragen');
    }
}
