<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;

/**
 * R39: only meaningful while the gallery has no version yet — a gallery
 * that already published, was withdrawn, or never entered pre-release has
 * nothing to sign up for, and this quietly does nothing rather than
 * throwing, so the controller's uniform response stays uniform.
 */
class SignUpForPrerelease
{
    public function __invoke(Gallery $gallery, string $email): void
    {
        if (! $gallery->isInPrerelease()) {
            return;
        }

        // insertOrIgnore, not firstOrCreate: the unique index on
        // (tenant_id, gallery_id, email) is what makes "already on the
        // list" a no-op rather than a QueryException on a genuine race
        // between two submits of the same address — one statement, no
        // read-then-write gap. Bypasses Eloquent's creating() hooks, so
        // tenant_id and the timestamps are filled by hand here the way
        // BelongsToTenant would have.
        GalleryPrereleaseSignup::query()->insertOrIgnore([
            'tenant_id' => config('tenancy.tenant_id'),
            'gallery_id' => $gallery->id,
            'email' => GalleryPrereleaseSignup::normalizeEmail($email),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
