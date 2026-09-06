<?php

namespace App\Notifications\Studio;

use App\Models\UserEmailVerification;
use App\Notifications\Contracts\Transactional;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioEmailVerification extends Notification implements Transactional
{
    public function __construct(
        private readonly string $name,
        private readonly string $code,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bestätige deine E-Mail-Adresse')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line('bitte bestätige die E-Mail-Adresse deines Auri-CRM-Kontos mit diesem Code:')
            ->line($this->code)
            ->line('Der Code gilt '.UserEmailVerification::VALID_FOR_MINUTES.' Minuten.')
            ->action('E-Mail-Adresse bestätigen', $this->url)
            ->line('Oder diesen Link öffnen:')
            ->line($this->url);
    }
}
