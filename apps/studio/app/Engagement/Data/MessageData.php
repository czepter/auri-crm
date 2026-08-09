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
    ) {}

    public static function fromModel(Message $message): self
    {
        return new self(
            id: $message->public_id,
            author: $message->contact_id !== null ? $message->contact->name : $message->author->name,
            fromStaff: $message->contact_id === null,
            body: $message->body,
            sentAt: $message->created_at->toIso8601String(),
        );
    }
}
