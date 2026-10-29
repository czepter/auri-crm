<?php

namespace App\Gallery\Actions;

use App\Gallery\Jobs\BuildZip;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;

/**
 * The dedupe half of #59's acceptance criterion — "a second request for the
 * same (version, scope, size) reuses the built archive." Finds-or-creates
 * against zip_builds' own (tenant, gallery_version, scope, size) unique
 * index, which is the actual dedupe mechanism, and only dispatches
 * App\Gallery\Jobs\BuildZip onto media.zip for a row that isn't already Ready
 * or already Building.
 */
class RequestZipBuild
{
    public function __invoke(GalleryVersion $version, ZipBuildScope $scope, ZipBuildSize $size): ZipBuild
    {
        $zipBuild = ZipBuild::query()->firstOrCreate(
            ['gallery_version_id' => $version->id, 'scope' => $scope, 'size' => $size],
            ['status' => ZipBuildStatus::Building],
        );

        if ($zipBuild->wasRecentlyCreated) {
            BuildZip::dispatch($zipBuild)->onQueue('media.zip');

            return $zipBuild;
        }

        if ($zipBuild->status === ZipBuildStatus::Failed) {
            // Retried in place — the unique index means this triple can
            // never get a second row, Ready or otherwise.
            $zipBuild->update(['status' => ZipBuildStatus::Building, 'error' => null]);
            BuildZip::dispatch($zipBuild)->onQueue('media.zip');
        }

        // Ready: already built, reused as-is. Building: another attempt is
        // already in flight — never dispatch a second one for the same row.
        return $zipBuild;
    }
}
