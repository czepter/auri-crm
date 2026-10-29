<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Gallery\Notifications\GalleryEmailVerificationLink;
use App\Notifications\Jobs\SendMailNotification;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Notification;

/**
 * Issues the email gate's verification link. Unlike RequestClientAccess,
 * there is no known-address list to avoid leaking — anyone holding the
 * gallery link may request one for any address they type — so this always
 * sends, with none of RequestClientAccess's uniform-timing machinery. G-07
 * adds rate limiting later; this story only gets the gate itself right.
 */
class RequestGalleryEmailVerification
{
    public function __invoke(Gallery $gallery, string $email): void
    {
        $token = bin2hex(random_bytes(32));

        $verification = GalleryEmailVerification::create([
            'gallery_id' => $gallery->id,
            'email' => $email,
            'token_hash' => GalleryEmailVerification::hash($token),
            'expires_at' => now()->addMinutes(GalleryEmailVerification::VALID_FOR_MINUTES),
        ]);

        SendMailNotification::dispatch(
            Notification::route('mail', $email),
            new GalleryEmailVerificationLink(
                $gallery,
                url("/galerien/{$gallery->public_id}/download/email/{$token}"),
            ),
        );

        AuditLogger::record(
            event: 'gallery.email_verification_issued',
            context: ['gallery' => $gallery->public_id],
            subject: $verification->public_id,
        );
    }
}
