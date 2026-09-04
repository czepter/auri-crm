<?php

namespace App\Gallery\Data;

use App\Gallery\Models\GalleryPreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GalleryPresetData extends Data
{
    /** @param  array<string, string>|null  $design */
    public function __construct(
        public string $id,
        public string $name,
        public ?array $design,
        public string $expiryMode,
        public ?int $expiryDays,
        public ?bool $requiresSettledBalance,
        public ?bool $downloadsEnabled,
        public ?bool $listsEnabled,
        public ?bool $commentsEnabled,
    ) {}

    public static function fromModel(GalleryPreset $preset): self
    {
        return new self(
            id: $preset->public_id,
            name: $preset->name,
            design: $preset->design,
            expiryMode: $preset->expiry_mode,
            expiryDays: $preset->expiry_days,
            requiresSettledBalance: $preset->requires_settled_balance,
            downloadsEnabled: $preset->downloads_enabled,
            listsEnabled: $preset->lists_enabled,
            commentsEnabled: $preset->comments_enabled,
        );
    }
}
