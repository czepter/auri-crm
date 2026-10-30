<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Support\LiveUpdate;

class UpdateGallery
{
    /** @param array<string, mixed> $fields */
    public function __invoke(Gallery $gallery, array $fields): Gallery
    {
        $gallery->update($fields);

        LiveUpdate::publish();

        return $gallery;
    }
}
