<?php

namespace App\Integration\Data;

use App\Crm\Models\ShotListItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShotListItemReadData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $checkedOff,
    ) {}

    public static function fromModel(ShotListItem $item): self
    {
        return new self(
            id: $item->public_id,
            label: $item->label,
            checkedOff: $item->isCheckedOff(),
        );
    }
}
