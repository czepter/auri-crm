<?php

namespace App\Crm\Screens;

use App\Gallery\Models\Gallery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One gallery as the project's Galerien screen reads it. Narrower than
 * GalleryRowData, which answers the studio-wide list: here the project is
 * given, and what is not is the state, the shoot it belongs to, and whether
 * the client can see it.
 */
#[TypeScript]
class ProjectGalleryData extends Data
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $shootId,
        /** 'draft', 'prerelease', 'published' or 'withdrawn'. */
        public string $state,
        public int $assetCount,
        public ?int $version,
        public ?string $expiresAt,
        public bool $inPortal,
    ) {}

    public static function fromModel(Gallery $gallery): self
    {
        $published = $gallery->currentVersion !== null;

        return new self(
            id: $gallery->public_id,
            title: $gallery->title,
            shootId: $gallery->shoot?->public_id,
            state: match (true) {
                $gallery->isWithdrawn() => 'withdrawn',
                $published => 'published',
                $gallery->isInPrerelease() => 'prerelease',
                default => 'draft',
            },
            assetCount: $gallery->assets()->inDraft()->count(),
            version: $gallery->currentVersion?->version,
            expiresAt: $gallery->expires_at?->format('Y-m-d'),
            // What the portal actually shows for a project: a published
            // gallery the studio has not pulled back (Portal\GalleryController).
            inPortal: $published && ! $gallery->isWithdrawn(),
        );
    }
}
