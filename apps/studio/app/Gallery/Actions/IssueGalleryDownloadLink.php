<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;

/**
 * Mints the "Herunterladen" mail's own link — signed by a stored token
 * rather than Illuminate\Support\Facades\URL::temporarySignedRoute(), which
 * GalleryDownloadController::partUrl() can use because it always runs
 * inside a real portal request. NotifyZipWaiters calls this from a queued
 * job, which the worker always processes on the admin surface (see
 * docker-compose.yml) — a surface whose router has never heard of a portal
 * route name to sign against. A stored token needs no route table on either
 * end, only a database both surfaces already share.
 */
class IssueGalleryDownloadLink
{
    public function __invoke(Gallery $gallery): string
    {
        $token = bin2hex(random_bytes(32));

        GalleryDownloadLink::create([
            'gallery_id' => $gallery->id,
            'token_hash' => GalleryDownloadLink::hash($token),
            'expires_at' => now()->addDays(GalleryDownloadLink::VALID_FOR_DAYS),
        ]);

        return rtrim((string) config('portal.url'), '/')."/galerien/{$gallery->public_id}/download/mail/{$token}";
    }
}
