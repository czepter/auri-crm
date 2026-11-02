<?php

namespace App\Gallery\Actions;

use App\Gallery\Jobs\BuildZip;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildWaiter;

/**
 * "Bilder vorbereiten", für Portal und Betrachter gleich. Reuses a Ready build
 * rather than making a second archive of the same bytes; a Failed one is
 * rebuilt, and anything older than $freshHours is rebuilt too, because its
 * parts are stale.
 */
class StartZipBuild
{
    public function __invoke(
        GalleryVersion $version,
        ZipBuildSize $size,
        ?GalleryList $list,
        ?string $waiterEmail,
        int $freshHours,
    ): void {
        $build = ZipBuild::query()->firstOrNew([
            'gallery_version_id' => $version->id,
            'scope' => $list === null ? ZipBuildScope::All : ZipBuildScope::ListSelection,
            'size' => $size,
            'gallery_list_id' => $list?->id,
        ]);

        if ($build->exists && $build->status === ZipBuildStatus::Ready && $build->updated_at->gt(now()->subHours($freshHours))) {
            return;
        }

        // Ein laufender Build wird weder neu gestartet noch seine Teile gelöscht; nach dem
        // media.zip-Timeout gilt er als tot.
        $running = $build->exists && $build->status === ZipBuildStatus::Building
            && $build->updated_at->gt(now()->subSeconds(config('horizon.defaults.supervisor-media-zip.timeout')));

        if (! $running) {
            $build->fill(['status' => ZipBuildStatus::Building, 'error' => null])->save();
            $build->parts()->delete();
        }

        // Ein ZipBuild gehört niemandem — zwei Gäste, die dasselbe anfordern,
        // teilen sich die Bände —, also hängt die Zustelladresse an einer
        // eigenen Zeile und nicht am Bau.
        if ($waiterEmail !== null) {
            ZipBuildWaiter::query()->firstOrCreate([
                'zip_build_id' => $build->id,
                'gallery_guest_id' => GalleryGuest::forGallery($version->gallery, $waiterEmail)->id,
            ]);
        }

        if (! $running) {
            BuildZip::dispatch($build);
        }
    }
}
