<?php

namespace App\Engagement\Data;

use App\Engagement\Models\Thread;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ThreadData extends Data
{
    /**
     * @param  list<MessageData>  $messages
     * @param  list<string>  $recipients
     */
    public function __construct(
        public string $id,
        public string $subject,
        public array $messages,
        public array $recipients,
    ) {}

    public static function fromModel(Thread $thread): self
    {
        return new self(
            id: $thread->public_id,
            subject: $thread->subject,
            messages: $thread->messages->map(MessageData::fromModel(...))->all(),
            // Exactly who a reply would reach: SendStaffReply's own set, so
            // the composer cannot name somebody the mail never goes to.
            recipients: $thread->participants()
                ->whereNotNull('contact_id')
                ->with('contact')
                ->get()
                ->pluck('contact')
                ->filter(fn ($contact) => $contact !== null && filled($contact->email))
                ->map(fn ($contact) => $contact->name)
                ->values()
                ->all(),
        );
    }
}
