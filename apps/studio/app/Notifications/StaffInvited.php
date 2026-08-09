<?php

namespace App\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Models\StaffInvitation;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation mail. A notification rather than a Mailable so it needs no
 * blade view, and so E-07's notification service inherits it rather than
 * finding a second delivery path already in place.
 */
class StaffInvited extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly StaffInvitation $invitation,
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
            ->subject('Zugang zum Studio von Atelier Nordlicht')
            ->greeting("Hallo {$this->invitation->name},")
            ->line("{$this->invitation->invitedBy->name} hat dich als {$this->invitation->role->label()} eingeladen.")
            ->action('Zugang einrichten', $this->url)
            ->line('Der Link gilt '.StaffInvitation::VALID_FOR_DAYS.' Tage.')
            // Said plainly here, because meeting an authenticator app
            // unannounced is where people abandon a setup link.
            ->line('Im Anschluss richtest du die Bestätigung in zwei Schritten ein — ohne sie ist das Konto nicht nutzbar.');

        // T-06: from/reply-to/BCC and the signature+legal footer, resolved
        // per tenant at send time rather than from MAIL_FROM_ADDRESS.
        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
