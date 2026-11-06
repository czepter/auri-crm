<?php

namespace App\Privacy\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Ein Kontakt, dessen Anfragen alle verloren oder abgelehnt enden; die Seite filtert nach Frist. */
#[TypeScript]
class RetentionCandidateData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public ?string $text,
        public string $outcome,
        public string $outcomeLabel,
        public int $idleMonths,
        public string $idleSince,
        public bool $invoiced,
        public int $leads,
        public int $messages,
        public int $offers,
        public int $galleries,
    ) {}
}
