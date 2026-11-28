<?php

namespace App\Gallery\Actions;

use App\Gallery\Notifications\GalleryZipReady;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildWaiter;
use App\Notifications\Jobs\SendMailNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sagt allen Bescheid, die auf dieses Archiv gewartet haben.
 *
 * notified_at wird gesetzt, bevor die Mail rausgeht: läuft ein Bau nach
 * einem Fehlschlag ein zweites Mal bis Ready, darf niemand die Nachricht
 * zweimal bekommen.
 */
class NotifyZipWaiters
{
    public function __construct(
        private readonly IssueGalleryDownloadLink $issueLink,
    ) {}

    public function __invoke(ZipBuild $zipBuild): void
    {
        $gallery = $zipBuild->galleryVersion->gallery;
        $parts = $zipBuild->parts()->count();

        ZipBuildWaiter::query()
            ->where('zip_build_id', $zipBuild->id)
            ->whereNull('notified_at')
            ->with('guest')
            ->get()
            ->each(function (ZipBuildWaiter $waiter) use ($zipBuild, $gallery, $parts) {
                $waiter->forceFill(['notified_at' => now()])->save();

                if ($waiter->guest === null) {
                    return;
                }

                // Der Gast-Link trägt Bau und Gast, also einer je Wartendem.
                $url = ($this->issueLink)($gallery, $zipBuild->guests ? $zipBuild : null, $waiter->guest);

                SendMailNotification::dispatch(
                    Notification::route('mail', $waiter->guest->email),
                    new GalleryZipReady($gallery, $url, $parts),
                );
            });
    }
}
