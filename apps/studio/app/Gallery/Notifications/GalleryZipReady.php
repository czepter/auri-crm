<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
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
 * Der Link (IssueGalleryDownloadLink) führt zurück auf den Download-Schirm
 * und nicht auf ein Band: die Bänder tragen eigene, signierte Adressen, die
 * der Schirm bei jedem Aufruf neu ausstellt. Der Mail-Link selbst trägt ein
 * gespeichertes Token mit eigener Gültigkeit — GalleryDownloadLink::
 * VALID_FOR_DAYS —, unabhängig davon, ob das Archiv dahinter noch frisch ist
 * oder auf Anfrage neu gebaut wird.
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
                ? 'Eure Bilder stehen zum Download bereit.'
                : "Eure Bilder stehen in {$this->parts} Teilen zum Download bereit.")
            ->action('Herunterladen', $this->url)
            ->line('Der Link ist '.GalleryDownloadLink::VALID_FOR_DAYS.' Tage gültig.');

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
