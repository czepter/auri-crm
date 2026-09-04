<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Das Archiv steht.
 *
 * Ein Bau über 14 GB dauert Minuten, und niemand sitzt so lange vor einer
 * Seite und lädt neu. Genau dafür wird beim Herunterladen nach einer
 * Adresse gefragt — ohne diese Mail wäre sie eine Erhebung ohne Zweck.
 *
 * Der Link führt zurück auf den Download-Schirm und nicht auf ein Band:
 * die Bänder tragen signierte, 24 Stunden gültige Adressen, die eine Mail
 * überleben würde, und wie viele es sind, weiß der Schirm besser.
 */
class GalleryZipReady extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly string $url,
        private readonly int $parts,
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
            ->subject("Deine Bilder aus „{$this->gallery->title}“ stehen bereit")
            ->greeting('Hallo,')
            ->line($this->parts === 1
                ? 'Das Archiv ist fertig gepackt.'
                : "Das Archiv ist fertig gepackt — {$this->parts} Teile.")
            ->action('Herunterladen', $this->url)
            ->line('Die Links gelten 24 Stunden.');

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
