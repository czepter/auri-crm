<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryDownloadGate;
use App\Models\StudioSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wenige Wochen vor Ablauf, einmal je Galerie (RemindGalleryExpiry), an den
 * Hauptkontakt des Projekts. Wie GalleryPublished Neuigkeit zur Buchung und
 * damit nicht Transactional: Einstellungen und Ruhezeiten dürfen sie halten.
 */
class GalleryExpiring extends Notification
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(private readonly Gallery $gallery) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->gallery->expires_at->locale('de')->isoFormat('D. MMMM YYYY');

        $message = (new MailMessage)
            ->subject("„{$this->gallery->title}“ ist noch bis zum {$date} online")
            ->greeting("Hallo {$notifiable->name},")
            ->line("deine Galerie „{$this->gallery->title}“ von ".StudioSettings::current()->display_name." ist noch bis zum {$date} online.")
            ->line(GalleryDownloadGate::allows($this->gallery)
                ? 'Bis dahin kannst du die Bilder ansehen und herunterladen.'
                : 'Bis dahin kannst du die Bilder ansehen.')
            ->line('Viel Freude mit deinen Bildern.')
            ->action('Galerie ansehen', $this->gallery->publicUrl());

        return $this->appendStudioMailIdentity($this->appendUnsubscribe($message, $notifiable));
    }
}
