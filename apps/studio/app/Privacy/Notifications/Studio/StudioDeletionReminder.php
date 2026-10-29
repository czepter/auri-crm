<?php

namespace App\Privacy\Notifications\Studio;

use App\Notifications\Contracts\Transactional;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioDeletionReminder extends Notification implements Transactional
{
    public function __construct(
        private readonly string $name,
        private readonly CarbonInterface $date,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dein Studio wird morgen gelöscht')
            ->view('mail.product-message', ['brand' => 'Auri CRM'])
            ->greeting("Hallo {$this->name},")
            ->line("morgen, am {$this->date->format('d.m.Y')}, löscht Auri CRM dein Studio mit allen Daten.")
            ->line('Deine Daten lädst du bis dahin unter Einstellungen › Export.')
            ->action('Löschung abbrechen', url('/einstellungen/studio-loeschen'))
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
