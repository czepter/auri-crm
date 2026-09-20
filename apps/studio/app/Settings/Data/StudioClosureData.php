<?php

namespace App\Settings\Data;

use App\Settings\Models\StudioClosure;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A studio closure as the Verfügbarkeit settings pane sees it. `id` is the public_id. */
#[TypeScript]
class StudioClosureData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
        public string $startsOn,
        public string $endsOn,
        public ?string $message,
    ) {}

    public static function fromModel(StudioClosure $closure): self
    {
        return new self(
            id: $closure->public_id,
            label: $closure->label,
            startsOn: $closure->starts_on->format('Y-m-d'),
            endsOn: $closure->ends_on->format('Y-m-d'),
            message: $closure->message,
        );
    }
}
