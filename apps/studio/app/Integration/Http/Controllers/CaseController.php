<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\BookCase;
use App\Crm\Actions\CompleteCase;
use App\Crm\Enums\LeadSource;
use App\Crm\Models\ClientCase;
use App\Http\Controllers\Controller;
use App\Integration\Data\CaseDetailReadData;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LiveUpdate;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Taking an import back. A run that captured the wrong rows has no other way
 * out: every write on this surface so far only ever adds.
 *
 * There is no delete_lead beside this. A lead is born with its project and
 * means nothing without it, so the project is the unit that goes.
 *
 * What the project owns alone — the lead, the shoots, the notes, the contact
 * links, the tasks and the conversation — the database removes with it (every
 * case_id foreign key is cascadeOnDelete). The contacts stay: they are
 * people, reachable from other projects.
 */
class CaseController extends Controller
{
    /**
     * #400: name, type/type_other and lead source, merged rather than
     * replaced — App\Crm\Http\Controllers\LeadCaseController::update()
     * nulls type_other whenever `type` arrives without it, and the issue is
     * explicit that the api surface must not repeat that: only a key
     * actually present in the request is written, so ClientCase::booted()
     * remains the only thing that clears type_other, and only when the type
     * itself changed to something other than "Sonstige".
     */
    public function update(Request $request, ClientCase $case): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::exists('project_types', 'key')->where('is_active', true)],
            'type_other' => ['sometimes', 'nullable', 'string', 'max:120'],
            'source' => ['sometimes', 'required', Rule::enum(LeadSource::class)],
            'source_other' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $projectFields = Arr::only($validated, ['name', 'type', 'type_other']);

        if ($projectFields !== []) {
            $case->update($projectFields);
        }

        $leadFields = Arr::only($validated, ['source', 'source_other']);

        if ($leadFields !== [] && $case->lead !== null) {
            $case->lead->update($leadFields);
        }

        return response()->json([
            'id' => $case->public_id,
            'name' => $case->name,
            'type' => $case->type->key,
            'typeOther' => $case->type_other,
            'source' => $case->lead?->source?->value,
            'sourceOther' => $case->lead?->source_other,
        ]);
    }

    public function destroy(ClientCase $case): Response
    {
        $blocking = $case->deletionBlockers();

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                'project' => 'The project has a '.$blocking->join(', ', ' and ').' and cannot be deleted.',
            ]);
        }

        $case->delete();

        LiveUpdate::publish();

        return response()->noContent();
    }

    /**
     * #401: book/complete/reopen on the api surface, mirroring
     * App\Crm\Http\Controllers\CaseController — the same BookCase and
     * CompleteCase actions the admin buttons call, and the same
     * already-booked no-op BookCase itself guards.
     */
    public function book(Request $request, ClientCase $case, BookCase $book): JsonResponse
    {
        $book($case, $this->actor($request));

        return response()->json(['data' => CaseDetailReadData::fromModel($case)]);
    }

    public function complete(Request $request, ClientCase $case, CompleteCase $complete): JsonResponse
    {
        try {
            $complete($case, $this->actor($request));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $case->public_id, 'completedAt' => $case->completed_at?->toIso8601String()]]);
    }

    public function reopen(Request $request, ClientCase $case, CompleteCase $complete): JsonResponse
    {
        try {
            $complete->reopen($case, $this->actor($request));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['project' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $case->public_id, 'completedAt' => $case->completed_at?->toIso8601String()]]);
    }

    /** N-04: null for an ApiToken caller, the consenting staff member otherwise — see LeadController::actor(). */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }

    /**
     * Mirrors App\Crm\Http\Controllers\CaseController::togglePortalVisibility,
     * but takes the value rather than flipping it: a retried toggle after a
     * dropped response would land on the wrong state, which `idempotent`
     * (routes/api.php) must never allow.
     */
    public function setPortalVisibility(Request $request, ClientCase $case): JsonResponse
    {
        $visible = $request->validate(['visible' => ['required', 'boolean']])['visible'];

        $case->update(['portal_visible' => $visible]);

        $principal = $request->user('api');

        AuditLogger::record(
            event: $visible ? 'project.portal_shown' : 'project.portal_hidden',
            actor: $principal instanceof User ? $principal : null,
            subject: $case->public_id,
            projectId: $case->id,
        );

        return response()->json(['id' => $case->public_id, 'portalVisible' => $case->portal_visible]);
    }
}
