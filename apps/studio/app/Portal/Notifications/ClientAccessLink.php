<?php

namespace App\Portal\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Models\Contact;
use App\Notifications\Contracts\Transactional;
use App\Portal\Models\ClientAccess;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T-11: Transactional — the client is waiting on this magic link right now,
 * so no preference and no quiet-hours window ever holds it back.
 */
class ClientAccessLink extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Contact $contact,
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
            ->subject('Ihr Zugang zum Kundenbereich')
            ->greeting("Hallo {$this->contact->name},")
            ->action('Kundenbereich öffnen', $this->url)
            // Said plainly, because a link that stops working without warning
            // reads as a broken system rather than as a deliberate one.
            ->line('Der Link gilt '.ClientAccess::VALID_FOR_MINUTES.' Minuten und funktioniert einmal.')
            ->line('Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');

        // T-06: from/reply-to/BCC and the signature+legal footer, resolved
        // per tenant at send time rather than from MAIL_FROM_ADDRESS.
        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
