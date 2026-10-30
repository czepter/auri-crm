<?php

namespace App\Gallery\Actions;

use App\Gallery\Jobs\ProcessIngestedAsset;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;

/**
 * G-04: re-runs G-03's ingest pipeline for one Failed asset from the "needs
 * attention" tray (mockups/admin/05-medien.html).
 *
 * The Failed row is deleted rather than reused. IngestCompletedUpload's own
 * checksum dedup (assets_gallery_checksum_unique, and the `$existing` lookup
 * ahead of it) treats any row already at (gallery, checksum) as concluded
 * regardless of status — leaving the Failed row in place would make
 * ProcessIngestedAsset's re-run a silent no-op, exactly the idempotency trap
 * this story exists to route around. Deleting it frees the checksum for a
 * fresh Asset::create() on retry, and resetting the MultipartUpload's own
 * processed_at is what lets IngestCompletedUpload treat this as an
 * unprocessed upload again. The original bytes are still sitting under the
 * upload's quarantine key throughout — that action only ever moves them out
 * of quarantine on success, never on a Failed conclusion.
 */
class RetryFailedAsset
{
    public function __invoke(Asset $asset): void
    {
        $upload = MultipartUpload::query()
            ->where('asset_id', $asset->id)
            ->latest('id')
            ->first();

        abort_if($upload === null, 409, 'No upload session found to retry from.');

        $asset->delete();

        $upload->forceFill(['processed_at' => null, 'asset_id' => null])->save();

        ProcessIngestedAsset::dispatch($upload)->onQueue('media.ingest');
    }
}
