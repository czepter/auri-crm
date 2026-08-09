<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation mail. A notification rather than a Mailable so it needs no
 * blade view, and so E-07's notification service inherits it rather than
 * finding a second delivery path already in place.
 */
class StaffInvited extends Notification
{
    public function __construct(
        private readonly StaffInvitation $invitation,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Zugang zum Studio von Atelier Nordlicht')
            ->greeting("Hallo {$this->invitation->name},")
            ->line("{$this->invitation->invitedBy->name} hat dich als {$this->invitation->role->label()} eingeladen.")
            ->action('Zugang einrichten', $this->url)
            ->line('Der Link gilt '.StaffInvitation::VALID_FOR_DAYS.' Tage.')
            // Said plainly here, because meeting an authenticator app
            // unannounced is where people abandon a setup link.
            ->line('Im Anschluss richtest du die Bestätigung in zwei Schritten ein — ohne sie ist das Konto nicht nutzbar.');
    }
}
