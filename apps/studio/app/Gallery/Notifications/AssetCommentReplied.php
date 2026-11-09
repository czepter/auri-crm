<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Models\StudioSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gegenrichtung zu AssetCommented: das Studio hat auf den Kommentar eines
 * Portal-Kunden geantwortet. Nur an den Kontakt, nie an Gäste.
 */
class AssetCommentReplied extends Notification
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(
        private readonly Gallery $gallery,
        private readonly AssetComment $reply,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studio = StudioSettings::current()->display_name;

        $message = (new MailMessage)
            ->subject("Antwort auf deinen Kommentar in „{$this->gallery->title}“")
            ->greeting("Hallo {$notifiable->name},")
            ->line("{$studio} hat auf deinen Kommentar in „{$this->gallery->title}“ geantwortet:")
            ->line("„{$this->reply->body}“")
            ->action('Galerie ansehen', $this->gallery->publicUrl());

        return $this->appendStudioMailIdentity($this->appendUnsubscribe($message, $notifiable));
    }
}
