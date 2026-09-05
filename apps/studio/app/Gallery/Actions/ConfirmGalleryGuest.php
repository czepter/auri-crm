<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Models\GalleryGuest;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Nimmt Code oder Link entgegen und macht aus dem angelegten Gast einen
 * bestätigten.
 *
 * Fünf Fehlversuche je Vorgang, dann ist er verbraucht: sechs Ziffern sind
 * eine Million Möglichkeiten, aber ohne Zähler reichen ein paar Minuten.
 * Gezählt wird am Vorgang, also je Adresse und Galerie — nicht je IP. Der
 * Betrachter rendert serverseitig, alle Gäste kämen sonst als eine einzige
 * Adresse an, und der erste Tippfehler sperrte die Hochzeitsgesellschaft aus
 * (R44).
 */
class ConfirmGalleryGuest
{
    public const MAX_ATTEMPTS = 5;

    public function byCode(Gallery $gallery, string $email, string $code): ?GalleryGuest
    {
        $verification = GalleryEmailVerification::query()
            ->where('gallery_id', $gallery->id)
            ->where('email', mb_strtolower(trim($email)))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->latest('id')
            ->first();

        if ($verification === null) {
            return null;
        }

        // Erst zählen, dann prüfen: ein Abbruch mitten im Vorgang darf keinen
        // Freiversuch schenken.
        $verification->increment('attempts');

        if (! hash_equals((string) $verification->code_hash, GalleryEmailVerification::hash($code))) {
            return null;
        }

        return $this->consume($gallery, $verification);
    }

    public function byToken(Gallery $gallery, string $token): ?GalleryGuest
    {
        $verification = GalleryEmailVerification::query()
            ->where('gallery_id', $gallery->id)
            ->where('token_hash', GalleryEmailVerification::hash($token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        return $verification === null ? null : $this->consume($gallery, $verification);
    }

    private function consume(Gallery $gallery, GalleryEmailVerification $verification): ?GalleryGuest
    {
        $guest = GalleryGuest::query()
            ->where('gallery_id', $gallery->id)
            ->where('email', $verification->email)
            ->first();

        if ($guest === null) {
            return null;
        }

        $verification->forceFill(['consumed_at' => now()])->save();
        $guest->forceFill(['confirmed_at' => $guest->confirmed_at ?? now()])->save();

        AuditLogger::record(
            event: 'gallery.guest_confirmed',
            context: ['gallery' => $gallery->public_id],
            subject: $guest->public_id,
        );

        return $guest;
    }

    /**
     * Was dieser Browser anonym markiert und geschrieben hat, gehört ab jetzt
     * dem bestätigten Gast.
     *
     * Ohne das verliert jemand, der erst zehn Bilder markiert und dann zur
     * Anmeldung aufgefordert wird, genau diese zehn — und meldet sich
     * daraufhin nicht an.
     *
     * Herzen können kollidieren (asset_favorites_unique_guest_mark): liegt
     * unter der Adresse schon eins für dasselbe Bild, wird die anonyme Zeile
     * gelöscht statt umgeschrieben. Sichtbar ändert das nichts — markiert war
     * es vorher und nachher. Kommentare tragen keinen solchen Index und
     * wandern vollständig mit.
     */
    public function adopt(GalleryGuest $guest, string $anonymousId): void
    {
        if ($anonymousId === '' || $anonymousId === $guest->identifier) {
            return;
        }

        $versionIds = $guest->gallery->versions()->pluck('id');

        DB::transaction(function () use ($guest, $anonymousId, $versionIds) {
            AssetFavorite::query()
                ->whereIn('gallery_version_id', $versionIds)
                ->where('guest_identifier', $anonymousId)
                ->get()
                ->each(function (AssetFavorite $favorite) use ($guest) {
                    $taken = AssetFavorite::query()
                        ->where('gallery_version_id', $favorite->gallery_version_id)
                        ->where('asset_key', $favorite->asset_key)
                        ->where('guest_identifier', $guest->identifier)
                        ->exists();

                    $taken
                        ? $favorite->delete()
                        : $favorite->forceFill(['guest_identifier' => $guest->identifier])->save();
                });

            AssetComment::query()
                ->whereIn('gallery_version_id', $versionIds)
                ->where('guest_identifier', $anonymousId)
                ->update(['guest_identifier' => $guest->identifier]);
        });
    }
}
