<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One person the crew picker may offer for one shooting day, with the answer
 * the picker needs before it offers them: free, away, or standing on another
 * shoot that day. Built per shoot rather than per studio — availability is a
 * property of a date, which is why the studio-wide staff list could never
 * carry it.
 *
 * Warns, never blocks: same rule CheckDateAvailability states for every other
 * conflict it produces.
 */
#[TypeScript]
class CrewCandidateData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        /** 'free', 'absent' or 'busy'. */
        public string $status,
        /** The absence category, or what the other shoot is — null when free. */
        public ?string $note = null,
    ) {}
}
