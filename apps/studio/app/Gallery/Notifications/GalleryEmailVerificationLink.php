<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Models\StudioSettings;
use App\Notifications\Contracts\IgnoresClientMailSwitch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email gate's own link. Deliberately not the eventual download link
 * itself — that is G-11's, once a signed, time-boxed archive link exists to
 * send. This one only ever establishes identity: clicking it grants the
 * email claim (App\Gallery\Support\GalleryAccessSession), nothing more.
 */
class GalleryEmailVerificationLink extends Notification implements IgnoresClientMailSwitch
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Download aus Galerie „{$this->gallery->title}“ bestätigen")
            ->greeting('Hallo,')
            ->action('E-Mail-Adresse bestätigen', $this->url)
            ->line('Der Link gilt '.GalleryEmailVerification::VALID_FOR_MINUTES.' Minuten.')
            ->line(StudioSettings::current()->clientAddress() === 'du'
                ? 'Wenn du das nicht angefordert hast, kannst du diese E-Mail ignorieren.'
                : 'Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');

        return $this->appendStudioMailIdentity($message);
    }
}
