<?php

namespace App\Media\Data;

use App\Media\Models\Asset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of G-04's "needs attention" tray (mockups/admin/05-medien.html,
 * the `.dsec`/`.trow` block): a filename and the error
 * App\Media\Actions\IngestCompletedUpload gave up with, plus the public id
 * the retry action needs and nothing a client could enumerate with.
 */
#[TypeScript]
class FailedAssetData extends Data
{
    public function __construct(
        public string $publicId,
        public string $filename,
        public string $error,
    ) {}

    public static function fromAsset(Asset $asset): self
    {
        return new self(
            publicId: $asset->public_id,
            filename: $asset->filename,
            error: $asset->error ?? '',
        );
    }
}
