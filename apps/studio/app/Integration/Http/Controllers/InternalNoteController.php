<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * I-03: "add internal note" on the api surface — the same write
 * App\Crm\Http\Controllers\InternalNoteController::store makes, minus the
 * staff-session author neither an ApiToken nor this surface has. See the
 * migration that dropped internal_notes.author_id's NOT NULL for why that's
 * safe, and InternalNoteData::fromNote for how the admin screen renders one.
 */
class InternalNoteController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $project->notes()->create([
            'author_id' => null,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'id' => $note->public_id,
            'body' => $note->body,
            'writtenAt' => $note->created_at->toIso8601String(),
        ], 201);
    }
}
