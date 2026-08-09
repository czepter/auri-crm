<?php

namespace App\Crm\Data;

use App\Crm\Models\InternalNote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class InternalNoteData extends Data
{
    public function __construct(
        public string $id,
        public string $body,
        public string $author,
        public string $writtenAt,
    ) {}

    public static function fromNote(InternalNote $note): self
    {
        return new self(
            id: $note->public_id,
            body: $note->body,
            author: $note->author->name,
            writtenAt: $note->created_at->format('Y-m-d'),
        );
    }
}
