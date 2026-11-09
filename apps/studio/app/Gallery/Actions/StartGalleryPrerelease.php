<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class StartGalleryPrerelease
{
    public function __construct(private readonly DuplicateGallery $duplicate) {}

    /**
     * Mit $own läuft die Vorabversion in einer Kopie, und die Galerie selbst bleibt unberührt.
     *
     * @param  list<string>|null  $assetIds  welche Bilder die Kopie bekommt, null für alle
     */
    public function __invoke(Gallery $gallery, ?User $actor, bool $own = false, ?array $assetIds = null): Gallery
    {
        // A gallery already published has nothing left for R39's "no version
        // yet" to mean, and an external gallery owns no draft to be
        // "prepared" — Gallery::publish() itself refuses one for the same
        // reason.
        if ($gallery->currentVersion !== null || $gallery->is_external) {
            throw ValidationException::withMessages(['gallery' => __('gallery.prerelease_unavailable')]);
        }

        if ($own) {
            $gallery = ($this->duplicate)($gallery, $actor, __('gallery.prerelease_suffix'), $assetIds);
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
