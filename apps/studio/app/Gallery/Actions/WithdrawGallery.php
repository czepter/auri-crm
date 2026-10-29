<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;

class WithdrawGallery
{
    public function __invoke(Gallery $gallery, ?User $actor): Gallery
    {
        $gallery->forceFill(['unpublished_at' => now()])->save();

        // R38: withdrawal is the one gesture that means "stop showing this
        // to people outside" — a preview link already sitting in a couple's
        // inbox must stop opening the draft the same moment, not keep
        // working as the longest-lived credential left standing.
        $gallery->rotatePreviewPass();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: $actor,
            context: [],
            subject: $gallery->public_id,
        );

        return $gallery;
    }
}
