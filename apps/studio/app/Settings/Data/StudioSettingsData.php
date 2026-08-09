<?php

namespace App\Settings\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The settings row as the admin surface sees it. `id` is the public_id —
 * never listed or linked to, but present anyway per the Definition of Done:
 * no integer identifier in any Inertia prop, this row included.
 */
#[TypeScript]
class StudioSettingsData extends Data
{
    public function __construct(
        public string $id,
        public ?string $displayName,
    ) {}
}
