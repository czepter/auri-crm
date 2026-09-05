<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryEmailVerification;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sechs Ziffern und derselbe Vorgang als Link. Die Ziffern stehen zuerst:
 * wer die Mail auf dem Telefon liest, sitzt meistens vor der Galerie am
 * Rechner, und ein Klick öffnet sie dort im falschen Browser.
 */
class GalleryGuestCode extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly string $code,
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
            ->subject("Dein Code für die Galerie „{$this->gallery->title}“")
            ->greeting('Hallo,')
            ->line('Dein Code:')
            ->line("**{$this->code}**")
            ->line('Gültig '.GalleryEmailVerification::VALID_FOR_MINUTES.' Minuten.')
            ->action('Oder hier bestätigen', $this->url)
            ->line('Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
