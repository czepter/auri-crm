<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\GalleryExpiring;
use App\Notifications\NotificationService;

class RemindGalleryExpiry
{
    public const DAYS_AHEAD = 14;

    public function __construct(private readonly NotificationService $notifications) {}

    /** @return int Zahl der erinnerten Kunden */
    public function __invoke(): int
    {
        $sent = 0;

        Gallery::query()
            ->where('expiry_reminder', true)
            ->whereNull('expiry_reminded_at')
            ->whereNull('unpublished_at')
            ->whereHas('currentVersion')
            ->whereBetween('expires_at', [now(), now()->addDays(self::DAYS_AHEAD)])
            ->with('case')
            ->each(function (Gallery $gallery) use (&$sent) {
                $contact = $gallery->case?->primaryContact();

                if (filled($contact?->email)) {
                    $this->notifications->send($contact, new GalleryExpiring($gallery));
                    $sent++;
                }

                $gallery->forceFill(['expiry_reminded_at' => now()])->save();
            });

        return $sent;
    }
}
