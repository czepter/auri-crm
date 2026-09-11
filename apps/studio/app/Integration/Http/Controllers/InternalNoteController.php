<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * I-03: "add internal note" on the api surface — the same write
 * App\Crm\Http\Controllers\InternalNoteController::store makes. author_id is
 * null for an ApiToken caller (I-01), which has no staff session to credit
 * — see the migration that dropped internal_notes.author_id's NOT NULL for
 * why that's safe, and InternalNoteData::fromNote for how the admin screen
 * renders one. N-04 adds a second kind of api-surface caller, a staff User
 * authenticated by their own OAuth token, and that one *is* the author: the
 * note should read exactly as if they had typed it on the project screen.
 */
class InternalNoteController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $principal = $request->user('api');

        $note = $project->notes()->create([
            'author_id' => $principal instanceof User ? $principal->id : null,
            'body' => $validated['body'],
            'occurred_at' => $validated['occurred_at'] ?? null,
        ]);

        LiveUpdate::publish();

        return response()->json([
            'id' => $note->public_id,
            'body' => $note->body,
            'writtenAt' => $note->created_at->toIso8601String(),
            'occurredAt' => $note->happenedAt()->toIso8601String(),
        ], 201);
    }
}
