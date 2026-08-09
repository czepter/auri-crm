<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email gate's own link. Deliberately not the eventual download link
 * itself — that is G-11's, once a signed, time-boxed archive link exists to
 * send. This one only ever establishes identity: clicking it grants the
 * email claim (App\Gallery\Support\GalleryAccessSession), nothing more.
 */
class GalleryEmailVerificationLink extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly string $url,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Bitte bestätigen Sie Ihre E-Mail-Adresse')
            ->greeting('Hallo,')
            ->line("um Bilder aus der Galerie „{$this->gallery->title}“ herunterzuladen, bestätigen Sie bitte Ihre E-Mail-Adresse.")
            ->action('E-Mail-Adresse bestätigen', $this->url)
            ->line('Der Link gilt '.GalleryEmailVerification::VALID_FOR_MINUTES.' Minuten.')
            ->line('Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
