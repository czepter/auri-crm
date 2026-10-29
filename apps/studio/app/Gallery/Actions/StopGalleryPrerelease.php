<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;

class StopGalleryPrerelease
{
    public function __invoke(Gallery $gallery, ?User $actor): Gallery
    {
        $gallery->forceFill(['prerelease_at' => null])->save();

        // GalleryPrereleaseSignup's own docblock: the waiting list belongs
        // to this pre-release window, not to the gallery for ever. Any row
        // still here was never notified (a notified one deletes itself,
        // see PublishGallery), so there is nothing to preserve.
        $gallery->prereleaseSignups()->delete();

        AuditLogger::record(
            event: 'gallery.prerelease_stopped',
            actor: $actor,
            context: [],
            subject: $gallery->public_id,
        );

        return $gallery;
    }
}
