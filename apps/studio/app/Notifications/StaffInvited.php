<?php

namespace App\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Models\StaffInvitation;
use App\Notifications\Contracts\Transactional;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation mail. A notification rather than a Mailable so it needs no
 * blade view, and so E-07's notification service inherits it rather than
 * finding a second delivery path already in place.
 *
 * T-11: Transactional — the invited person is waiting on this link right
 * now, so no preference and no quiet-hours window ever holds it back.
 */
class StaffInvited extends Notification implements Transactional
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
        $studio = $this->settings->displayName();

        $message = (new MailMessage)
            ->subject($studio === null ? 'Dein Zugang zum Studio' : "Dein Zugang zu {$studio}")
            ->view('mail.blocks-message', [
                'blocks' => [
                    [
                        'heading' => 'Deine Termine im eigenen Kalender',
                        'body' => 'Die Shootings, für die du eingeteilt bist, kannst du in deiner Kalender-App abonnieren.',
                        'buttonLabel' => 'Kalender abonnieren',
                        'buttonUrl' => url('/profil/kalender'),
                    ],
                    [
                        'heading' => 'Antworten am Auftrag',
                        'body' => 'Kundenantworten landen am Auftrag, auch wenn der Kunde aus seinem eigenen Mailprogramm schreibt.',
                        'buttonLabel' => 'Zum Studio',
                        'buttonUrl' => url('/'),
                    ],
                ],
            ])
            ->greeting("Hallo {$this->invitation->name},")
            ->line("{$this->invitation->invitedBy->name} hat dich als {$this->invitation->role->label()} eingeladen. Schön, dass du dabei bist.")
            ->line('Der Link gilt '.StaffInvitation::VALID_FOR_DAYS.' Tage.')
            ->action('Zugang einrichten', $this->url);

        // T-06: from/reply-to/BCC and the signature+legal footer, resolved
        // per tenant at send time rather than from MAIL_FROM_ADDRESS.
        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
