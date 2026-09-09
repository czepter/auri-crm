<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Shoot;
use App\Crm\Support\CrmVocabulary;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
