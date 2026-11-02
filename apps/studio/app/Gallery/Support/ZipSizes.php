<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildSize;
use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;

/**
 * Bytes per size option for one set of keys, off Asset::byte_size and the
 * widest WebP derivative's own bytes — the same two numbers ZipVolumePlanner
 * plans volumes with, so the figure on the line is the figure that gets
 * transferred.
 */
class ZipSizes
{
    /**
     * ponytail: two queries per selection row, so a client with four lists
     * pays eight. Fine at a gallery's size; group them if a venue with forty
     * lists ever appears.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    public static function for(Gallery $gallery, array $keys): array
    {
        $assets = Asset::query()
            ->where('gallery_id', $gallery->id)
            ->whereIn('key', $keys)
            ->where('status', AssetStatus::Ready)
            ->get();

        $web = AssetDerivative::query()
            ->whereIn('asset_id', $assets->pluck('id'))
            ->where('format', 'webp')
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($derivatives) => (int) $derivatives->sortByDesc('width')->first()?->bytes);

        return [
            ZipBuildSize::Original->value => (int) $assets->sum('byte_size'),
            ZipBuildSize::Web->value => (int) $web->sum(),
        ];
    }
}
