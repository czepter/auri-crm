<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\DraftDeviation;
use Illuminate\Validation\ValidationException;

/**
 * Ein Knopf, zwei Ausgänge. Eine Version, die sich in nichts von ihrer
 * Vorgaengerin unterscheidet, waere Rauschen in einer Historie, die ein Paar
 * ueber Jahre begleitet.
 */
class PublishGallery
{
    public function __invoke(Gallery $gallery): ?GalleryVersion
    {
        if (! DraftDeviation::of($gallery)) {
            $gallery->forceFill(['unpublished_at' => null])->save();

            return null;
        }

        if ($gallery->draftAssets()->isEmpty()) {
            throw ValidationException::withMessages(['gallery' => __('gallery.empty_draft')]);
        }

        $version = $gallery->publish();

        $gallery->forceFill(['unpublished_at' => null])->save();

        return $version;
    }
}
