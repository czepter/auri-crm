<?php

namespace App\Engagement\Data;

use App\Engagement\Models\Message;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MessageData extends Data
{
    public function __construct(
        public string $id,
        public string $author,
        public bool $fromStaff,
        public string $body,
        public string $sentAt,
        // E-11: what the transport reported. Null for a message the client
        // wrote — we never delivered that one — and `failed` where a dead
        // address needs to be visible in the conversation rather than in a
        // log nobody opens.
        public ?string $deliveryState = null,
        // Which project this was said on. Only the contact-level history
        // needs it; a thread already knows its own project, so it stays null
        // there rather than repeating the same string on every row.
        public ?string $projectName = null,
        public ?string $projectId = null,
        // E-13: set while the message is still waiting to go out. A
        // scheduled mail is visible work in the thread rather than a hidden
        // timer, which is what makes cancelling it a thing anyone can find.
        public ?string $scheduledFor = null,
    ) {}

    public static function fromModel(Message $message): self
    {
        return new self(
            id: $message->public_id,
            author: $message->contact_id !== null ? $message->contact->name : $message->author->name,
            fromStaff: $message->contact_id === null,
            body: $message->body,
            sentAt: $message->created_at->toIso8601String(),
            deliveryState: $message->delivery_state,
            scheduledFor: $message->delivery_state === null
                ? $message->scheduled_for?->toIso8601String()
                : null,
        );
    }

    /** The same row, told from the contact screen, where the project is not implied. */
    public static function withProject(Message $message): self
    {
        $project = $message->thread->case;

        return new self(
            id: $message->public_id,
            author: $message->contact_id !== null ? $message->contact->name : $message->author->name,
            fromStaff: $message->contact_id === null,
            body: $message->body,
            sentAt: $message->created_at->toIso8601String(),
            deliveryState: $message->delivery_state,
            projectName: $project?->name,
            projectId: $project?->public_id,
        );
    }
}
