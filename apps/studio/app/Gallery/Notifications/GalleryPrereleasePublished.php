<?php

namespace App\Gallery\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Gallery\Models\Gallery;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * R42: the one message a pre-release signup ever gets — sent once, from
 * PublishGallery, to every address not yet marked notified_at. Same shape
 * as GalleryEmailVerificationLink: mail only, on-demand (the address has no
 * Contact or User behind it), studio identity appended.
 *
 * ShouldQueue: PublishGallery can be fanning this out to however many
 * addresses signed up while the gallery was in pre-release, and a studio
 * clicking Veröffentlichen should not wait on that send.
 *
 * No unsubscribe line — this is not a list, it is the single reply to a
 * single request, and the address is never contacted again after it fires.
 */
class GalleryPrereleasePublished extends Notification implements ShouldQueue
{
    use AppliesStudioMailIdentity;
    use Queueable;

    public function __construct(
        private readonly Gallery $gallery,
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
            ->subject("Galerie „{$this->gallery->title}“ ist online")
            ->greeting('Hallo,')
            ->line('Eure Galerie ist ab jetzt zu sehen.')
            ->action('Galerie ansehen', $this->gallery->publicUrl());

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
