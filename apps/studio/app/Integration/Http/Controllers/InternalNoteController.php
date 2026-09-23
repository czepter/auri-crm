<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Crm\Models\InternalNote;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * I-03: "add internal note" on the api surface — the same write
 * App\Crm\Http\Controllers\InternalNoteController::store makes. author_id is
 * null for an ApiToken caller (I-01), which has no staff session to credit
 * — see the migration that dropped internal_notes.author_id's NOT NULL for
 * why that's safe, and InternalNoteData::fromNote for how the admin screen
 * renders one. N-04 adds a second kind of api-surface caller, a staff User
 * authenticated by their own OAuth token, and that one *is* the author: the
 * note should read exactly as if they had typed it on the project screen.
 *
 * Update and delete keep the author and follow the admin controller: any
 * holder of manage_notes may change any note, audit_logs records who did.
 */
class InternalNoteController extends Controller
{
    /** Oldest first, on when it happened — the order the admin thread reads in. */
    public function index(ClientCase $case): JsonResponse
    {
        $notes = $case->notes()
            ->with('author')
            ->orderByRaw('coalesce(occurred_at, created_at)')
            ->get();

        return response()->json(['data' => $notes->map($this->present(...))]);
    }

    public function store(Request $request, ClientCase $case): JsonResponse
    {
        $validated = $this->validated($request);

        $principal = $request->user('api');

        $note = $case->notes()->create([
            'author_id' => $principal instanceof User ? $principal->id : null,
            'body' => $validated['body'],
            'occurred_at' => $validated['occurred_at'] ?? null,
        ]);

        LiveUpdate::publish();

        return response()->json($this->present($note), 201);
    }

    public function update(Request $request, InternalNote $note): JsonResponse
    {
        $note->update($this->validated($request));

        LiveUpdate::publish();

        return response()->json($this->present($note));
    }

    public function destroy(InternalNote $note): Response
    {
        $note->delete();

        LiveUpdate::publish();

        return response()->noContent();
    }

    /**
     * @return array{body: string, occurred_at?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
    }

    /**
     * @return array{id: string, body: string, author: string|null, writtenAt: string, occurredAt: string}
     */
    private function present(InternalNote $note): array
    {
        return [
            'id' => $note->public_id,
            'body' => $note->body,
            'author' => $note->author?->name,
            'writtenAt' => $note->created_at->toIso8601String(),
            'occurredAt' => $note->happenedAt()->toIso8601String(),
        ];
    }
}
