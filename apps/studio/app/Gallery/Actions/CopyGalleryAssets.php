<?php

namespace App\Gallery\Actions;

use App\Gallery\Jobs\CopyGalleryAsset;
use App\Gallery\Models\Gallery;

/**
 * Start und Wiederaufnahme sind derselbe Aufruf: eingereiht wird, was die
 * Kopie noch nicht hat. Die Auswahl steht an der Galerie, damit "Fortsetzen"
 * sie nicht erneut von jemandem verlangt.
 */
class CopyGalleryAssets
{
    public function __invoke(Gallery $copy): int
    {
        $source = $copy->copySource;

        if ($source === null) {
            return 0;
        }

        $done = $copy->assets()->whereNotNull('copied_from_asset_id')->pluck('copied_from_asset_id');

        $pending = $source->assets()->inDraft()
            ->when($copy->copy_asset_ids !== null, fn ($query) => $query->whereIn('public_id', $copy->copy_asset_ids))
            ->whereNotIn('id', $done)
            ->get();

        $pending->each(fn ($asset) => CopyGalleryAsset::dispatch($copy, $asset));

        return $pending->count();
    }
}
