<?php

namespace App\Integration\Data;

use App\Gallery\Models\Gallery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A gallery as GET /galleries hands it to an api-surface token: project,
 * current version and draft count, the three things #405 asked the list
 * for. GalleryDetailData is the single-gallery answer.
 *
 * Requires `project:id,public_id`, `currentVersion` and the `asset_count`
 * (assets in draft) eager loaded — see GalleryController::index().
 */
#[TypeScript]
class GalleryReadData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $projectId,
        public ?int $currentVersion,
        public int $draftAssetCount,
        public string $status,
        public string $updatedAt,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        return new self(
            id: $gallery->public_id,
            title: $gallery->title ?? '',
            projectId: $gallery->project?->public_id,
            currentVersion: $gallery->currentVersion?->version,
            draftAssetCount: (int) $gallery->asset_count,
            status: self::status($gallery),
            updatedAt: $gallery->updated_at->toIso8601String(),
        );
    }

    public static function status(Gallery $gallery): string
    {
        return match (true) {
            $gallery->isWithdrawn() => 'withdrawn',
            $gallery->isInPrerelease() => 'prerelease',
            $gallery->currentVersion !== null => 'published',
            default => 'draft',
        };
    }
}
