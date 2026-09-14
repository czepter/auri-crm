<?php

namespace App\Integration\Data;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryView;
use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What GET /galleries/{gallery} answers with — GalleryReadData plus the
 * status, slug, expiry, downloads and comments #405 asked a single gallery
 * for. The counts mirror GalleryRecordController's own figures block.
 */
#[TypeScript]
class GalleryDetailData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $projectId,
        public ?int $currentVersion,
        public int $draftAssetCount,
        public string $status,
        public string $updatedAt,
        public string $slug,
        public string $url,
        public bool $isExternal,
        public ?string $externalUrl,
        public ?string $expiresAt,
        public bool $downloadsEnabled,
        public bool $listsEnabled,
        public bool $commentsEnabled,
        public int $favoriteCount,
        public int $downloadCount,
        public int $commentCount,
        public int $viewCount,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        $version = $gallery->currentVersion;

        return new self(
            id: $gallery->public_id,
            title: $gallery->title ?? '',
            projectId: $gallery->project?->public_id,
            currentVersion: $version?->version,
            draftAssetCount: $gallery->draftAssets()->count(),
            status: GalleryReadData::status($gallery),
            updatedAt: $gallery->updated_at->toIso8601String(),
            slug: $gallery->slug,
            url: $gallery->publicUrl(),
            isExternal: $gallery->is_external,
            externalUrl: $gallery->external_url,
            expiresAt: $gallery->expires_at?->toIso8601String(),
            downloadsEnabled: $gallery->downloads_enabled,
            listsEnabled: $gallery->lists_enabled,
            commentsEnabled: $gallery->comments_enabled,
            favoriteCount: $version === null ? 0 : AssetFavorite::query()->where('gallery_version_id', $version->id)->count(),
            downloadCount: $version === null ? 0 : DownloadEvent::query()
                ->whereIn('zip_build_id', ZipBuild::query()->where('gallery_version_id', $version->id)->select('id'))
                ->count(),
            commentCount: $version === null ? 0 : AssetComment::query()->where('gallery_version_id', $version->id)->count(),
            viewCount: GalleryView::query()->where('gallery_id', $gallery->id)->count(),
        );
    }
}
