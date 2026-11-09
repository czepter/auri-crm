<?php

namespace App\Gallery\Data;

use App\Gallery\Models\Gallery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class GalleryRowData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $projectName,
        public ?string $projectType,
        public ?string $projectTypeLabel,
        public int $assetCount,
        public ?int $version,
        public ?string $expiresAt,
        public ?string $cover,
        public string $status,
        public string $downloadRule,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        return new self(
            id: $gallery->public_id,
            title: $gallery->title,
            projectName: $gallery->case?->name,
            projectType: $gallery->case?->type?->key,
            projectTypeLabel: $gallery->case?->type?->name,
            assetCount: $gallery->asset_count,
            version: $gallery->currentVersion?->version,
            expiresAt: $gallery->expires_at?->format('Y-m-d'),
            cover: $gallery->coverAsset === null ? null : "/galerien/{$gallery->public_id}/bilder/{$gallery->coverAsset->public_id}/vorschau?w=400&fmt=jpeg",
            status: $gallery->lifecycle(),
            downloadRule: $gallery->download_rule,
        );
    }
}
