<?php

namespace App\Media\Actions;

use App\Gallery\Actions\IssueGalleryDownloadLink;
use App\Gallery\Notifications\GalleryZipReady;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildWaiter;
use App\Notifications\Jobs\SendMailNotification;
use App\Settings\Support\StudioSettingsResolver;
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
        private readonly StudioSettingsResolver $settings,
        private readonly IssueGalleryDownloadLink $issueLink,
    ) {}

    public function __invoke(ZipBuild $zipBuild): void
    {
        $gallery = $zipBuild->galleryVersion->gallery;
        $url = ($this->issueLink)($gallery);
        $parts = $zipBuild->parts()->count();

        ZipBuildWaiter::query()
            ->where('zip_build_id', $zipBuild->id)
            ->whereNull('notified_at')
            ->with('guest')
            ->get()
            ->each(function (ZipBuildWaiter $waiter) use ($gallery, $url, $parts) {
                $waiter->forceFill(['notified_at' => now()])->save();

                if ($waiter->guest === null) {
                    return;
                }

                SendMailNotification::dispatch(
                    Notification::route('mail', $waiter->guest->email),
                    new GalleryZipReady($gallery, $url, $parts, $this->settings),
                );
            });
    }
}
