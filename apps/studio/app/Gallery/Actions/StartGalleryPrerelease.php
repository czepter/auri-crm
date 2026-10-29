<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class StartGalleryPrerelease
{
    public function __invoke(Gallery $gallery, ?User $actor): Gallery
    {
        // A gallery already published has nothing left for R39's "no version
        // yet" to mean, and an external gallery owns no draft to be
        // "prepared" — Gallery::publish() itself refuses one for the same
        // reason.
        if ($gallery->currentVersion !== null || $gallery->is_external) {
            throw ValidationException::withMessages(['gallery' => __('gallery.prerelease_unavailable')]);
        }

        $gallery->forceFill(['prerelease_at' => now()])->save();

        AuditLogger::record(
            event: 'gallery.prerelease_started',
            actor: $actor,
            context: [],
            subject: $gallery->public_id,
        );

        return $gallery;
    }
}
