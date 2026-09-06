<?php

namespace App\Notifications\Studio;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudioReady extends Notification
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $inboundAddress,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Deine Adressen bei Auri CRM')
            ->view('mail.blocks-message', [
                'brand' => 'Auri CRM',
                'blocks' => [
                    [
                        'heading' => 'Anfragen per Mail',
                        'body' => $this->inboundAddress === null
                            ? 'Deine Adresse für Anfragen findest du im Posteingang.'
                            : $this->inboundAddress.'<br>Die kannst du auf deiner Website verlinken.',
                        'buttonLabel' => 'Posteingang öffnen',
                        'buttonUrl' => '/posteingang',
                    ],
                    [
                        'heading' => 'Dein Kundenportal',
                        'body' => config('portal.url').'<br>Gebuchte Kunden bekommen ihren Zugang per Mail.',
                        'buttonLabel' => 'Portal ansehen',
                        'buttonUrl' => config('portal.url'),
                    ],
                ],
            ])
            ->greeting("Hallo {$this->name},")
            ->line('die Einrichtung steht. Dein Studio hat dabei zwei eigene Adressen bekommen.')
            ->action('Erste Anfrage anlegen', '/anfragen/neu')
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
