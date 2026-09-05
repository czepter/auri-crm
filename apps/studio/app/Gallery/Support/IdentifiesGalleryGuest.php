<?php

namespace App\Gallery\Support;

use App\Gallery\Models\GalleryGuest;
use App\Gallery\Models\GalleryVersion;
use Illuminate\Http\Request;

/**
 * Wer ruft hier an? Die Antwort steckt im signierten Gastpass, den der
 * Betrachter mitschickt.
 *
 * Als Feld beziehungsweise Abfrageparameter und nicht als eigener Kopfzeile:
 * die Kopfzeile `Authorization` trägt bereits den Ansichtspass, und ein
 * zweiter eigener Name müsste erst durch config/cors.php.
 */
trait IdentifiesGalleryGuest
{
    protected function guest(Request $request, GalleryVersion $version): ?GalleryGuest
    {
        $token = (string) ($request->input('guest_pass') ?? $request->query('guest_pass', ''));

        if ($token === '') {
            return null;
        }

        $identifier = app(GalleryGuestPass::class)->identify($version->gallery, $token);

        if ($identifier === null) {
            return null;
        }

        return GalleryGuest::query()
            ->where('gallery_id', $version->gallery_id)
            ->where('identifier', $identifier)
            ->whereNotNull('confirmed_at')
            ->first();
    }
}
