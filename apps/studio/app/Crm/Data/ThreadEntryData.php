<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a project's internal trace: a note somebody wrote, an event the
 * system recorded, or a task that got done. `system` separates the two ways it
 * reads — a bubble against a quiet line.
 */
#[TypeScript]
class ThreadEntryData extends Data
{
    public function __construct(
        public string $key,
        public ?string $at,
        public string $body,
        public ?string $author,
        public bool $system,
        // The note's public id, what edit and delete address; null on the rest.
        public ?string $note = null,
    ) {}
}
