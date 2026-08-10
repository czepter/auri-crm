<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * I-03: "capture lead" and "advance stage" on the api surface — both call the
 * exact same Actions the admin capture form (InquiryCaptureController) and
 * board (LeadStageController) already do, so the funnel's rules live in one
 * place regardless of which surface triggered them. $actor is null on every
 * call here — see CaptureInquiry's and AdvanceLeadStage's own handling of a
 * caller that is an ApiToken, not a staff User.
 */
class LeadController extends Controller
{
    public function store(CaptureInquiryData $data, CaptureInquiry $capture): JsonResponse
    {
        $project = $capture($data, null);
        $lead = $project->lead;

        return response()->json([
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'lead' => ['id' => $lead->public_id, 'stage' => $lead->stage->value, 'source' => $lead->source->value],
        ], 201);
    }

    public function advanceStage(Request $request, Lead $lead, AdvanceLeadStage $advance): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(LeadStage::class)],
        ]);

        try {
            $advance($lead, LeadStage::from($validated['stage']), null);
        } catch (DomainException $e) {
            // Same mapping LeadStageController gives the board: a rejected
            // transition is a validation failure, not a 500.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return response()->json(['id' => $lead->public_id, 'stage' => $lead->stage->value]);
    }
}
