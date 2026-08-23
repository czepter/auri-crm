<?php

namespace App\Media\Data;

use App\Gallery\Models\Gallery;
use App\Media\Actions\StartOrResumeMultipartUpload;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the ingest screen (mockups/admin/05-medien.html, "Ziel" card) names as
 * the destination for an import: the project and shoot a gallery belongs to,
 * never a storage path.
 */
#[TypeScript]
class MediaIngestTargetData extends Data
{
    public function __construct(
        public string $galleryId,
        public string $galleryTitle,
        // Nullable seit eine Galerie ohne Buchung stehen darf; die "Ziel"-Karte
        // lässt die Zeile dann weg.
        public ?string $projectId,
        public ?string $projectName,
        public ?string $shootId,
        public ?string $shootLabel,
        public int $partSize,
    ) {}

    public static function fromGallery(Gallery $gallery): self
    {
        return new self(
            galleryId: $gallery->public_id,
            galleryTitle: $gallery->title ?? 'Entwurf',
            projectId: $gallery->project?->public_id,
            projectName: $gallery->project?->name,
            shootId: $gallery->shoot?->public_id,
            shootLabel: $gallery->shoot?->label,
            partSize: StartOrResumeMultipartUpload::PART_SIZE,
        );
    }
}
