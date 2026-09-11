<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\CaptureInquiry;
use App\Crm\Data\CaptureInquiryData;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * I-03: "capture lead" and "advance stage" on the api surface — both call the
 * exact same Actions the admin capture form (InquiryCaptureController) and
 * board (LeadStageController) already do, so the funnel's rules live in one
 * place regardless of which surface triggered them. $actor is null for an
 * ApiToken caller (I-01) — see CaptureInquiry's and AdvanceLeadStage's own
 * handling of that — but N-04 gives the api surface a second kind of caller,
 * a staff User authenticated by their own OAuth token, and that one *is* the
 * actor: the whole point of a per-user token over a shared machine one is
 * that the action stays attributable to the person who authorised it.
 */
class LeadController extends Controller
{
    public function store(Request $request, CaptureInquiryData $data, CaptureInquiry $capture): JsonResponse
    {
        $project = $capture($data, $this->actor($request));
        $lead = $project->lead;

        return response()->json([
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'lead' => ['id' => $lead->public_id, 'stage' => $lead->stage->key, 'source' => $lead->source->value],
        ], 201);
    }

    /**
     * The same capture, once per row.
     *
     * One transaction, for the reason CommitHistoricalImport gives: a run
     * that wrote 140 rows and refused the 141st leaves nothing to tell the
     * imported rows from the ones that never landed, and a second attempt
     * duplicates whatever the first one kept.
     *
     * Rows are validated one at a time rather than through one `leads.*`
     * rule set: contact_name is `required_without:contact_id`, and under a
     * wildcard that reference resolves against the top level, never the
     * sibling in the same row.
     *
     * Every row is checked before the first one is written, because the
     * transaction only takes back the database: CaptureInquiry dispatches
     * LeadCaptured, the studio's mail about it is out of the building
     * immediately, and a rollback cannot recall it. Refusing the batch
     * before anything is captured is what keeps that mail from naming a
     * lead nobody can open.
     */
    public function storeBulk(Request $request, CaptureInquiry $capture): JsonResponse
    {
        $request->validate(['leads' => ['required', 'array', 'min:1', 'max:500']]);

        $rows = [];

        foreach ($request->input('leads') as $index => $row) {
            try {
                $rows[] = CaptureInquiryData::validateAndCreate($row);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(
                    collect($e->errors())
                        ->mapWithKeys(fn (array $messages, string $field) => ["leads.{$index}.{$field}" => $messages])
                        ->all(),
                );
            }
        }

        $actor = $this->actor($request);

        $captured = DB::transaction(fn () => array_map(function (CaptureInquiryData $data) use ($capture, $actor) {
            $project = $capture($data, $actor);

            return [
                'project' => ['id' => $project->public_id, 'name' => $project->name],
                'lead' => ['id' => $project->lead->public_id, 'stage' => $project->lead->stage->key],
            ];
        }, $rows));

        return response()->json(['captured' => count($captured), 'leads' => $captured], 201);
    }

    public function advanceStage(Request $request, Lead $lead, AdvanceLeadStage $advance, CrmVocabulary $stages): JsonResponse
    {
        $validated = $request->validate([
            'stage' => [
                'required',
                Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Lead->value)->where('is_active', true),
            ],
            // Since when it stands there, for a move entered after the fact. A
            // stage below the current one is a correction and takes back the
            // booking with it — see AdvanceLeadStage.
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $advance(
                $lead,
                $stages->byKey(StagePipeline::Lead, $validated['stage']),
                $this->actor($request),
                isset($validated['entered_at']) ? CarbonImmutable::parse($validated['entered_at']) : null,
            );
        } catch (DomainException $e) {
            // Same mapping LeadStageController gives the board: a rejected
            // transition is a validation failure, not a 500.
            throw ValidationException::withMessages(['stage' => $e->getMessage()]);
        }

        return $this->state($lead);
    }

    /** T-20's close, on the api surface. */
    public function markLost(Request $request, Lead $lead, AdvanceLeadStage $advance): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(LeadOutcome::class)],
            'reason' => ['required', 'string', 'max:255'],
            'lost_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $advance->markLost(
                $lead,
                $validated['reason'],
                $this->actor($request),
                LeadOutcome::from($validated['outcome']),
                isset($validated['lost_at']) ? CarbonImmutable::parse($validated['lost_at']) : null,
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
        }

        return $this->state($lead);
    }

    /** The correction: a lead closed by mistake stands in its stage again. */
    public function reopen(Request $request, Lead $lead, AdvanceLeadStage $advance): JsonResponse
    {
        try {
            $advance->reopen($lead, $this->actor($request));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
        }

        return $this->state($lead);
    }

    private function state(Lead $lead): JsonResponse
    {
        return response()->json([
            'id' => $lead->public_id,
            'stage' => $lead->stage->key,
            'enteredAt' => $lead->stage_entered_at?->toIso8601String(),
            'lostAt' => $lead->lost_at?->toIso8601String(),
        ]);
    }

    /** N-04: null for an ApiToken (or any other non-User) caller, the consenting staff member otherwise. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
