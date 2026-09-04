<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGuest;
use Illuminate\Support\Facades\Config;

/**
 * Wer der Gast ist, in einer Form, die eine Herkunftsgrenze übersteht.
 *
 * Derselbe Weg wie GalleryViewPass, aus demselben Grund: der Betrachter ist
 * ein eigener Astro-Prozess auf eigenem Host, kein Cookie von hier erreicht
 * ihn, und die api-Fläche ist absichtlich zustandslos. Also reist die
 * Aussage als signierte Zeichenkette und nicht als Sitzung — gespeichert
 * wird nichts, die Signatur ist der Nachweis.
 *
 * Drei Teile, jeder mit Grund:
 *
 * Die Galerie, damit eine Anmeldung nur dort gilt, wo sie erteilt wurde —
 * eine Bestätigung für fremde Galerien mitgelten zu lassen wäre eine
 * Einwilligung, die niemand gegeben hat.
 *
 * Die Kennung des Gastes, denn genau sie tragen seine Herzen und Kommentare.
 *
 * Ein Ablauf. Deutlich länger als der Ansichtspass: der Pass regelt, ob
 * jemand hineinsieht, dieser hier, ob er seinen eigenen Verlauf wiederfindet
 * — und der soll noch da sein, wenn das Paar drei Wochen später erneut
 * hineinschaut.
 */
class GalleryGuestPass
{
    public const VALID_FOR_DAYS = 90;

    public function issue(Gallery $gallery, GalleryGuest $guest): string
    {
        $payload = implode('.', [
            $gallery->public_id,
            $guest->identifier,
            now()->addDays(self::VALID_FOR_DAYS)->timestamp,
        ]);

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * Die Kennung, die $token für $gallery ausweist — oder null.
     *
     * Jeder Fehlschlag antwortet gleich: ein abgelaufenes und ein gefälschtes
     * Token sind von außen nicht zu unterscheiden.
     */
    public function identify(Gallery $gallery, ?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 4) {
            return null;
        }

        [$galleryId, $identifier, $expires, $signature] = $parts;

        if (! hash_equals($this->sign("{$galleryId}.{$identifier}.{$expires}"), $signature)) {
            return null;
        }

        if ($galleryId !== $gallery->public_id || (int) $expires <= now()->timestamp) {
            return null;
        }

        return $identifier;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('app.key'));
    }
}
