<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\DesignatePrimaryShoot;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Integration\Data\ShootReadData;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The production pipeline on the api surface, mirroring App\Crm\Http\
 * Controllers\ShootController: the same AdvanceShootStatus, the same guards,
 * the same mapping of a refused move onto a validation error rather than a
 * 500. The read side (/shoots) has been here since I-02; these are the writes
 * that go with it.
 */
class ShootController extends Controller
{
    /**
     * A second shooting day on a project that already has one. CaptureInquiry
     * makes exactly one shoot and marks it primary; a wedding split over two
     * days — Trauung on one, Feier on another — has no way to reach the
     * second from here otherwise.
     *
     * Mirrors App\Crm\Http\Controllers\ShootController::store: the same
     * rules, the same nullable date, and the model's own defaults for status,
     * is_primary and sort_order — the primary one is already taken.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            // Nullable, like the admin screen's own store — the project's
            // type names a default (Shoot::kindFor).
            'kind' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'call_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:call_time'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:255'],
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $shoot = $project->shoots()->create(
            Arr::except($validated, 'entered_at')
            + ['kind' => $validated['kind'] ?? Shoot::kindFor($project->type)]
            + array_filter(['status_entered_at' => $validated['entered_at'] ?? null])
        );
        $shoot->setRelation('project', $project);

        return response()->json(['data' => ShootReadData::fromModel($shoot)], 201);
    }

    /**
     * #363/#400: the fields that only ever existed on create until now.
     * `sometimes` throughout and Arr::only() on the write, so a caller
     * resending one field never wipes the rest the way LeadCaseController::
     * update()'s unconditional venue/time null-out does (#400's own note).
     */
    public function update(Request $request, Shoot $shoot): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'required', 'string', 'max:255'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'call_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'venue_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue_city' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validated !== []) {
            $shoot->update($validated);
        }

        return response()->json(['data' => ShootReadData::fromModel($shoot->fresh(['project:id,public_id']))]);
    }

    /** #400: an accidental second day. Mirrors App\Crm\Http\Controllers\ShootController::destroy(). */
    public function destroy(Shoot $shoot): Response
    {
        abort_if($shoot->is_primary, 422);

        $shoot->delete();

        return response()->noContent();
    }

    /** #400: hand the "Haupttermin" designation to a different shoot. */
    public function designatePrimary(Request $request, Shoot $shoot, DesignatePrimaryShoot $designate): JsonResponse
    {
        return $this->attempt('shoot', fn () => $designate($shoot, $this->actor($request)), $shoot);
    }

    public function advance(Request $request, Shoot $shoot, AdvanceShootStatus $advance, CrmVocabulary $stages): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::exists('stages', 'key')->where('pipeline', StagePipeline::Project->value)->where('is_active', true),
            ],
            'entered_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->attempt('status', fn () => $advance(
            $shoot,
            $stages->byKey(StagePipeline::Project, $validated['status']),
            $this->actor($request),
            isset($validated['entered_at']) ? CarbonImmutable::parse($validated['entered_at']) : null,
        ), $shoot);
    }

    public function cancel(Request $request, Shoot $shoot, AdvanceShootStatus $advance): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'cancelled_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->attempt('reason', fn () => $advance->cancel(
            $shoot,
            $validated['reason'],
            $this->actor($request),
            isset($validated['cancelled_at']) ? CarbonImmutable::parse($validated['cancelled_at']) : null,
        ), $shoot);
    }

    public function uncancel(Request $request, Shoot $shoot, AdvanceShootStatus $advance): JsonResponse
    {
        return $this->attempt('reason', fn () => $advance->uncancel($shoot, $this->actor($request)), $shoot);
    }

    private function attempt(string $field, callable $act, Shoot $shoot): JsonResponse
    {
        try {
            $act();
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return response()->json([
            'id' => $shoot->public_id,
            'status' => $shoot->status->key,
            'enteredAt' => $shoot->status_entered_at?->toIso8601String(),
            'cancelledAt' => $shoot->cancelled_at?->toIso8601String(),
        ]);
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
