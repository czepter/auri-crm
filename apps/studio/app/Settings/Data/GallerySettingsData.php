<?php

namespace App\Settings\Data;

use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * P-09 (#228): what a new gallery starts as, so a studio stops setting it per
 * delivery. Its own screen rather than a fieldset on Portal — a gallery is
 * delivered from /galerien, not from the portal's own pane, and the two share
 * nothing but the client who ends up looking at both.
 */
#[TypeScript]
class GallerySettingsData extends Data
{
    public function __construct(
        public string $id,
        public ?int $expiryDays,
        public bool $requiresSettledBalance,
        public bool $downloadsEnabled,
        public bool $listsEnabled,
        public bool $commentsEnabled,
        /** @var array<string, string> */
        public array $design,
    ) {}

    public static function fromResolver(StudioSettingsResolver $settings): self
    {
        return new self(
            id: $settings->publicId(),
            expiryDays: $settings->galleryDefaults()['expiryDays'],
            requiresSettledBalance: $settings->galleryDefaults()['requiresSettledBalance'],
            downloadsEnabled: $settings->galleryDownloadsEnabled(),
            listsEnabled: $settings->galleryListsEnabled(),
            commentsEnabled: $settings->galleryCommentsEnabled(),
            design: $settings->galleryDesign(),
        );
    }
}
