<?php

namespace App\Staff\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioWelcome extends Notification
{
    public function __construct(private readonly string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Willkommen bei Auri CRM')
            ->view('mail.blocks-message', [
                'brand' => 'Auri CRM',
                'blocks' => [
                    [
                        'heading' => 'Ein eigener Bereich für deine Kunden',
                        'body' => 'Angebot, Vertrag und Rechnung liegen im Kundenportal bereit. Deine Kunden melden sich <strong>ohne Passwort</strong> an, unterschreiben den Vertrag im Browser und zahlen per Überweisung oder Karte.',
                        'buttonLabel' => 'Portal einrichten',
                        'buttonUrl' => url('/einstellungen/portal'),
                    ],
                    [
                        'heading' => 'Galerien, die Gäste ohne Konto sehen',
                        'body' => 'Fertige Bilder gehen als Galerie raus statt als Link mit Ablaufdatum. Gäste öffnen sie <strong>ohne Konto</strong>, markieren Favoriten und laden ihre Auswahl als ZIP herunter.',
                        'buttonLabel' => 'Galerie anlegen',
                        'buttonUrl' => url('/galerien/neu'),
                    ],
                    [
                        'heading' => 'Anfragen und Antworten in einem Posteingang',
                        'body' => 'Dein Studio hat eine <strong>eigene Mailadresse für Anfragen</strong>. Was dort ankommt, landet im Posteingang. Antworten deiner Kunden hängen sich von selbst an den richtigen Auftrag.',
                        'buttonLabel' => 'Posteingang ansehen',
                        'buttonUrl' => url('/posteingang'),
                    ],
                ],
            ])
            ->greeting("Hallo {$this->name},")
            ->line('willkommen bei Auri CRM. Ab jetzt liegt alles zu deinen Aufträgen an einem Ort, von der ersten Anfrage bis zu den fertigen Bildern. Drei Funktionen, die du vielleicht noch nicht kennst:')
            ->action('Studio öffnen', url('/'))
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
