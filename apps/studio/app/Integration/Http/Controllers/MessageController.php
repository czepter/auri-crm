<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Engagement\Actions\RecordMessage;
use App\Engagement\Data\MessageData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Writing a conversation into the project after the fact — see RecordMessage.
 * A studio line needs a staff member to stand behind it (messages.author_id),
 * so only a caller signed in as one may record it; a bare ApiToken can record
 * what the client said.
 */
class MessageController extends Controller
{
    public function store(Request $request, ClientCase $case, RecordMessage $record): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', Rule::in(['client', 'studio'])],
            'body' => ['required', 'string', 'max:5000'],
            'sent_at' => ['nullable', 'date', 'before_or_equal:now'],
            'contact' => ['nullable', 'string'],
        ]);

        $principal = $request->user('api');

        $author = $validated['from'] === 'client'
            ? $record->contact($case, $validated['contact'] ?? null)
            : ($principal instanceof User ? $principal : null);

        if ($author === null) {
            throw ValidationException::withMessages($validated['from'] === 'client'
                ? ['contact' => 'Kein Kontakt dieses Projekts.']
                : ['from' => 'Studio-Nachrichten braucht einen Zugang mit Mitarbeiter.']);
        }

        $message = $record(
            $case,
            $author,
            $validated['body'],
            isset($validated['sent_at']) ? Carbon::parse($validated['sent_at']) : now(),
        );

        LiveUpdate::publish();

        return response()->json(MessageData::fromModel($message->load('contact', 'author')), 201);
    }
}
