<?php

namespace App\Portal\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Notifications\Contracts\Transactional;
use App\Portal\Models\ClientAccess;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The studio sending a contact into the portal. Transactional for
 * ClientAccessLink's reason: the mail is the credential. No unsubscribe line
 * for the same one.
 */
class PortalAccessInvitation extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(
        private readonly string $url,
        private readonly string $subject,
        private readonly ?string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->composedMail($this->subject, $this->body)
            ->action('Kundenbereich öffnen', $this->url)
            ->line('Der Link gilt '.ClientAccess::INVITATION_VALID_FOR_DAYS.' Tage und funktioniert einmal.');

        return $this->appendStudioMailIdentity($message);
    }
}
