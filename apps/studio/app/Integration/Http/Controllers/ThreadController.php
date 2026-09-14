<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Project;
use App\Engagement\Actions\PostMessage;
use App\Engagement\Data\MessageData;
use App\Engagement\Models\Message;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The api surface's send, through the same PostMessage the admin reply
 * (App\Engagement\Http\Controllers\ThreadController::reply) uses — a message
 * that leaves needs a staff member to stand behind it, unlike the api
 * surface's other message write (MessageController::store, RecordMessage),
 * which only writes history and sends nothing.
 */
class ThreadController extends Controller
{
    public function reply(Request $request, Project $project, PostMessage $post): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\{\{.*\}\}/s'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ], [
            'body.not_regex' => 'Die Nachricht enthält ein nicht aufgelöstes Feld.',
        ]);

        $actor = $this->actor($request);

        if ($actor === null) {
            throw ValidationException::withMessages(['actor' => 'Nachrichten senden braucht einen Zugang mit Mitarbeiter.']);
        }

        $thread = $project->latestThread()->first();

        abort_if($thread === null, 404);

        $message = $post(
            $thread,
            $actor,
            $validated['body'],
            isset($validated['scheduled_for']) ? Carbon::parse($validated['scheduled_for']) : null,
        );

        LiveUpdate::publish();

        return response()->json(MessageData::fromModel($message->load('author')), 201);
    }

    /** E-13, mirroring the admin cancel — see its own doc comment. */
    public function cancel(Message $message): Response
    {
        abort_if($message->scheduled_for === null || $message->delivery_state !== null, 422);

        $message->delete();

        LiveUpdate::publish();

        return response()->noContent();
    }

    /** N-04: the consenting staff member behind an OAuth token, null for a machine one. */
    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
