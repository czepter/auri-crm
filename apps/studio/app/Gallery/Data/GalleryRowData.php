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
        public int $assetCount,
        public ?int $version,
        public ?string $expiresAt,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        return new self(
            id: $gallery->public_id,
            title: $gallery->title,
            projectName: $gallery->project?->name,
            assetCount: count($gallery->assets ?? []),
            version: $gallery->currentVersion?->version,
            expiresAt: $gallery->expires_at?->format('Y-m-d'),
        );
    }
}
