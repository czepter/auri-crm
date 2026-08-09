<?php

namespace App\Engagement\Data;

use App\Engagement\Models\Thread;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ThreadData extends Data
{
    /** @param list<MessageData> $messages */
    public function __construct(
        public string $id,
        public string $subject,
        public array $messages,
    ) {}

    public static function fromModel(Thread $thread): self
    {
        return new self(
            id: $thread->public_id,
            subject: $thread->subject,
            messages: $thread->messages->map(MessageData::fromModel(...))->all(),
        );
    }
}
