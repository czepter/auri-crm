<?php

namespace App\Notifications\Studio;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioSetupPending extends Notification
{
    public function __construct(private readonly string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Noch ein paar Einstellungen')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line('um mit Auri CRM richtig durchzustarten, fehlen dir noch ein paar Einstellungen.')
            ->action('Weiter einrichten', url('/einrichtung/studio'));
    }
}
