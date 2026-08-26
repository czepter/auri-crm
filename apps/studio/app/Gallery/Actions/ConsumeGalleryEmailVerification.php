<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Models\GalleryGuest;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Spends an email-gate link and returns the address it verified, or null.
 * Single use enforced the same way ConsumeClientAccess enforces it: a
 * conditional update inside the transaction, not a read-then-write, so a
 * link raced twice can only win once.
 *
 * Legt dabei denselben GalleryGuest an, den der Betrachter kennt. Beide
 * Flächen tragen die Aussage unterschiedlich — hier eine Sitzung, dort ein
 * signierter Pass —, aber es ist dieselbe Tatsache über denselben Menschen.
 * Ohne das würde jemand, der sich im Betrachter angemeldet hat, im Portal
 * ein zweites Mal nach derselben Adresse gefragt.
 */
class ConsumeGalleryEmailVerification
{
    public function __invoke(Gallery $gallery, string $token): ?string
    {
        return DB::transaction(function () use ($gallery, $token) {
            $verification = GalleryEmailVerification::forToken($gallery, $token);

            if ($verification === null) {
                return null;
            }

            $claimed = GalleryEmailVerification::query()
                ->whereKey($verification->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($claimed === 0) {
                return null;
            }

            AuditLogger::record(
                event: 'gallery.email_verified',
                context: ['gallery' => $gallery->public_id],
                subject: $verification->public_id,
            );

            $guest = GalleryGuest::forGallery($gallery, $verification->email);
            $guest->forceFill(['confirmed_at' => $guest->confirmed_at ?? now()])->save();

            return $verification->email;
        });
    }
}
