<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;

/**
 * Eine Galerie ohne Titelbild ist kein Zustand, den jemand absichtlich
 * herstellt: das erste Bild wird eins, und wenn das gewählte verschwindet,
 * rückt das nächste nach.
 *
 * „Erstes" heißt alphabetisch erstes im ersten Set, nicht das erste, das den
 * Ingest schafft — parallele Multipart-Uploads werden in beliebiger
 * Reihenfolge fertig, und ein Titelbild, das bei jedem Hochladen ein anderes
 * ist, wäre schlimmer als keins.
 *
 * Ein gesetztes Titelbild wird nie überschrieben. Der nächste Stapel darf die
 * Wahl des Menschen nicht kassieren.
 */
class EnsureGalleryCover
{
    public function __invoke(Gallery $gallery): void
    {
        if ($gallery->cover_asset_id !== null) {
            return;
        }

        // sets() ordnet bereits nach position.
        foreach ($gallery->sets()->get() as $set) {
            /** @var GallerySet $set */
            $asset = $set->assets()->inDraft()->orderBy('filename')->first();

            if ($asset instanceof Asset) {
                $gallery->update([
                    'cover_asset_id' => $asset->id,
                    'cover_focal_x' => null,
                    'cover_focal_y' => null,
                ]);

                return;
            }
        }
    }
}
