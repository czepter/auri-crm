<?php

namespace App\Privacy\Actions;

use App\Gallery\Models\Gallery;
use App\Media\Enums\ZipBuildStatus;
use App\Models\User;
use App\Privacy\Jobs\BuildStudioExport;
use App\Privacy\Models\StudioExport;
use App\Settings\Enums\StudioExportKind;
use App\Support\AuditLogger;

/**
 * Je Art und Galerie baut nur ein Export; wer währenddessen noch einmal
 * klickt, bekommt den laufenden zurück. Die Zeile sichert das der Index
 * studio_exports_one_building zu.
 */
class RequestStudioExport
{
    public function __invoke(StudioExportKind $kind, ?Gallery $gallery, User $by): StudioExport
    {
        $key = ['kind' => $kind, 'gallery_id' => $gallery?->id];

        StudioExport::query()->where($key)->where('status', ZipBuildStatus::Failed)->delete();

        $export = StudioExport::query()->firstOrCreate(
            [...$key, 'status' => ZipBuildStatus::Building],
            ['requested_by_id' => $by->id],
        );

        if ($export->wasRecentlyCreated) {
            AuditLogger::record('studio_export.requested', $by, ['kind' => $kind->value], subject: $export->public_id);
            BuildStudioExport::dispatch($export)->onQueue('media.zip');
        }

        return $export;
    }
}
