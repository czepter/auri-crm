<?php

namespace App\Settings\Data;

use App\Gallery\Support\GallerySettings;
use App\Models\StudioSettings;
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
        public string $downloadRule,
        public bool $listsEnabled,
        public bool $commentsEnabled,
        /** @var array<string, string> */
        public array $design,
        public string $homeMode,
        public ?string $homeRedirectUrl,
        public bool $homeNoindex,
    ) {}

    public static function current(): self
    {
        return new self(
            id: StudioSettings::current()->public_id,
            expiryDays: GallerySettings::defaults()['expiryDays'],
            downloadRule: StudioSettings::current()->gallery_default_download_rule,
            listsEnabled: (bool) StudioSettings::current()->gallery_default_lists_enabled,
            commentsEnabled: (bool) StudioSettings::current()->gallery_default_comments_enabled,
            design: GallerySettings::design(),
            homeMode: StudioSettings::current()->gallery_home_mode,
            homeRedirectUrl: StudioSettings::current()->gallery_home_redirect_url,
            homeNoindex: (bool) StudioSettings::current()->gallery_home_noindex,
        );
    }
}
