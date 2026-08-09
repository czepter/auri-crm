<?php

namespace App\Portal\Notifications;

use App\Crm\Models\Contact;
use App\Portal\Models\ClientAccess;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAccessLink extends Notification
{
    public function __construct(
        private readonly Contact $contact,
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
            ->subject('Ihr Zugang zum Kundenbereich')
            ->greeting("Hallo {$this->contact->name},")
            ->line('hier ist Ihr Zugang zum Kundenbereich von Atelier Nordlicht.')
            ->action('Kundenbereich öffnen', $this->url)
            // Said plainly, because a link that stops working without warning
            // reads as a broken system rather than as a deliberate one.
            ->line('Der Link gilt '.ClientAccess::VALID_FOR_MINUTES.' Minuten und lässt sich einmal öffnen.')
            ->line('Wenn Sie das nicht angefordert haben, können Sie diese E-Mail ignorieren.');
    }
}
