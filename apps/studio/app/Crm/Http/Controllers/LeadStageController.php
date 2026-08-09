<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadStageController extends Controller
{
    public function update(Request $request, Lead $lead, AdvanceLeadStage $advance): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(LeadStage::class)],
        ]);

        try {
            $advance($lead, LeadStage::from($validated['stage']), auth('staff')->user());
        } catch (DomainException $e) {
            // A rejected transition is a validation failure to the studio, not
            // a 500 — the board renders it beside the card they clicked.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return redirect('/anfragen');
    }
}
