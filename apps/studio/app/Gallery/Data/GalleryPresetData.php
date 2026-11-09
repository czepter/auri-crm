<?php

namespace App\Gallery\Data;

use App\Gallery\Models\GalleryPreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GalleryPresetData extends Data
{
    /**
     * @param  array<string, string>|null  $design
     * @param  list<array{name: string, access: string}>|null  $sets
     * @param  list<string>|null  $downloadSizes
     * @param  array<string, array<string, bool>>|null  $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?array $design,
        public string $expiryMode,
        public ?int $expiryDays,
        public ?bool $downloadsEnabled,
        public ?bool $listsEnabled,
        public ?bool $commentsEnabled,
        public bool $isDefault,
        public ?array $sets,
        public ?string $viewMode,
        public ?string $downloadRule,
        public ?array $downloadSizes,
        public ?array $permissions,
        public ?bool $introEnabled,
        public ?bool $expiryReminder,
        public ?string $mailTemplateFamilyId,
    ) {}

    public static function fromModel(GalleryPreset $preset): self
    {
        return new self(
            id: $preset->public_id,
            name: $preset->name,
            design: $preset->design,
            expiryMode: $preset->expiry_mode,
            expiryDays: $preset->expiry_days,
            downloadsEnabled: $preset->downloads_enabled,
            listsEnabled: $preset->lists_enabled,
            commentsEnabled: $preset->comments_enabled,
            isDefault: $preset->is_default,
            sets: $preset->sets,
            viewMode: $preset->view_mode,
            downloadRule: $preset->download_rule,
            downloadSizes: $preset->download_sizes,
            permissions: $preset->permissions,
            introEnabled: $preset->intro_enabled,
            expiryReminder: $preset->expiry_reminder,
            mailTemplateFamilyId: $preset->mail_template_family_id,
        );
    }
}
