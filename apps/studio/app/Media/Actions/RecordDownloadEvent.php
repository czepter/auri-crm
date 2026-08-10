<?php

namespace App\Media\Actions;

use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;

/**
 * Writes one DownloadEvent row per request DownloadZipPartController serves
 * (G-11, #60) — the audit trail, not the bounded quantity. See
 * DownloadEvent::requesterDayCount() for what actually dedupes.
 */
class RecordDownloadEvent
{
    public function __invoke(ZipBuild $zipBuild, string $requesterIdentifier, ?string $ip): DownloadEvent
    {
        return DownloadEvent::create([
            'gallery_version_id' => $zipBuild->gallery_version_id,
            'zip_build_id' => $zipBuild->id,
            'requester_identifier' => $requesterIdentifier,
            'ip' => $ip,
            'request_date' => now()->toDateString(),
        ]);
    }
}
