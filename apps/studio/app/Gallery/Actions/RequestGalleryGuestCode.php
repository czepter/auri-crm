<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Notifications\GalleryGuestCode;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Notification;

/**
 * Fordert die Bestätigung für einen Gast an: legt ihn unbestätigt an und
 * schickt sechs Ziffern zum Abtippen, dazu denselben Vorgang als Link.
 *
 * Zwei Wege, ein Datensatz — wer die Mail auf dem Telefon liest und am
 * Rechner sitzt, tippt; wer beides in einer Hand hat, klickt.
 *
 * Wie RequestGalleryEmailVerification wird immer gesendet: es gibt keine
 * Liste bekannter Adressen, gegen die man prüfen könnte, und wer den
 * Galerielink hat, darf jede Adresse eintragen.
 */
class RequestGalleryGuestCode
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    public function __invoke(Gallery $gallery, string $email, string $name): GalleryEmailVerification
    {
        $email = mb_strtolower(trim($email));

        // Der Name wird bei jeder Anforderung mitgeführt: wer sich vertippt
        // hat, korrigiert ihn, indem er es noch einmal versucht.
        GalleryGuest::query()->updateOrCreate(
            ['gallery_id' => $gallery->id, 'email' => $email],
            ['name' => $name, 'identifier' => GalleryGuest::identifierFor($gallery, $email)],
        );

        $token = bin2hex(random_bytes(32));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = GalleryEmailVerification::create([
            'gallery_id' => $gallery->id,
            'email' => $email,
            'token_hash' => GalleryEmailVerification::hash($token),
            'code_hash' => GalleryEmailVerification::hash($code),
            'expires_at' => now()->addMinutes(GalleryEmailVerification::VALID_FOR_MINUTES),
        ]);

        Notification::route('mail', $email)->notify(new GalleryGuestCode(
            $gallery,
            $code,
            rtrim((string) config('services.gallery.url'), '/')."/g/{$gallery->public_id}?bestaetigung={$token}",
            $this->settings,
        ));

        AuditLogger::record(
            event: 'gallery.guest_code_issued',
            context: ['gallery' => $gallery->public_id],
            subject: $verification->public_id,
        );

        return $verification;
    }
}
