<?php

namespace App\Crm\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of one Übersicht column. Every row in mockup 01 has the same
 * anatomy — who it is about, what is owed, which fragment of that carries
 * emphasis, and the single action that discharges it — so the four columns
 * share one shape rather than four near-identical ones.
 *
 * The three text fields split the what clause across the emphasis: `what` is
 * the lead fragment, `emphasis` is the reason the row exists (the date or
 * count the column watches), rendered in <i>, and `whatTail` continues after
 * the emphasis. Any can be null; `what` is required, the other two frame it
 * as needed.
 *
 * `hot` is .abtn.hot — the action is also the overdue mark.
 */
#[TypeScript]
class DomainRowData extends Data
{
    public function __construct(
        public string $projectId,
        public string $who,
        public string $what,
        public ?string $emphasis,
        public ?string $whatTail,
        public ?RowActionData $action,
        public bool $hot = false,
    ) {}
}
