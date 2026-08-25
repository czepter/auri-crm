<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Notifications\GalleryPrereleasePublished;
use App\Gallery\Support\DraftDeviation;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\Notification;
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
            $gallery->forceFill(['unpublished_at' => null, 'prerelease_at' => null])->save();

            return null;
        }

        if ($gallery->draftAssets()->isEmpty()) {
            throw ValidationException::withMessages(['gallery' => __('gallery.empty_draft')]);
        }

        $version = $gallery->publish();

        $gallery->forceFill(['unpublished_at' => null, 'prerelease_at' => null])->save();

        $this->notifyPrereleaseSignups($gallery);

        return $version;
    }

    /**
     * R42: one mail per address, on the publish that first gives the
     * gallery something to show — never twice. notified_at is stamped in
     * the same update that reads which rows qualify, so a second publish()
     * racing this one (or arriving seconds later) finds nothing left
     * unmarked rather than a window to send through twice.
     */
    private function notifyPrereleaseSignups(Gallery $gallery): void
    {
        $emails = $gallery->prereleaseSignups()->notNotified()->pluck('email');

        if ($emails->isEmpty()) {
            return;
        }

        $gallery->prereleaseSignups()->notNotified()->update(['notified_at' => now()]);

        $settings = app(StudioSettingsResolver::class);

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(new GalleryPrereleasePublished($gallery, $settings));
        }
    }
}
