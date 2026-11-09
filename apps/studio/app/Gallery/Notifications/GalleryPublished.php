<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Gallery\Models\Gallery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Veröffentlichen from the compose screen, to the project's primary contact.
 * Not Transactional: like QuoteSent it is news about the booking, which
 * preferences, quiet hours and the unsubscribe link may all hold.
 */
class GalleryPublished extends Notification
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly string $subject,
        private readonly ?string $body,
        private readonly ?string $password = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->composedMail($this->subject, $this->body);

        if (filled($this->password)) {
            $message->line(__('gallery.mail_password', ['password' => $this->password]));
        }

        $message->action('Galerie ansehen', $this->gallery->publicUrl());

        return $this->appendStudioMailIdentity($this->appendUnsubscribe($message, $notifiable));
    }
}
