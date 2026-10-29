<?php

namespace App\Notifications\Studio;

use App\Models\StudioSettings;
use App\Notifications\Contracts\Transactional;
use App\Settings\Actions\StudioDeletionConfirmation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioDeletionCode extends Notification implements Transactional
{
    public function __construct(
        private readonly string $name,
        private readonly string $code,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Code zum Löschen deines Studios')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line('mit diesem Code merkst du dein Studio zum Löschen vor. Nach '.StudioSettings::DELETION_GRACE_DAYS.' Tagen werden alle Daten des Studios gelöscht.')
            ->line($this->code)
            ->line('Der Code gilt '.StudioDeletionConfirmation::VALID_FOR_MINUTES.' Minuten.')
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
