<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\DraftDeviation;

/**
 * Ein Knopf, zwei Ausgänge. Eine Version, die sich in nichts von ihrer
 * Vorgaengerin unterscheidet, waere Rauschen in einer Historie, die ein Paar
 * ueber Jahre begleitet.
 */
class PublishGallery
{
    public function __invoke(Gallery $gallery): ?GalleryVersion
    {
        $version = DraftDeviation::of($gallery) ? $gallery->publish() : null;

        $gallery->forceFill(['unpublished_at' => null])->save();

        return $version;
    }
}
