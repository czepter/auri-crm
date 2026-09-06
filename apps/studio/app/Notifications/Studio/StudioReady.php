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
        $blocks = [];

        if ($this->inboundAddress !== null) {
            $blocks[] = [
                'heading' => 'Anfragen per Mail',
                'body' => $this->inboundAddress.'<br>Die kannst du auf deiner Website verlinken.',
                'buttonLabel' => 'Posteingang öffnen',
                'buttonUrl' => url('/posteingang'),
            ];
        }

        $blocks[] = [
            'heading' => 'Dein Kundenportal',
            'body' => config('portal.url').'<br>Gebuchte Kunden bekommen ihren Zugang per Mail.',
            'buttonLabel' => 'Portal ansehen',
            'buttonUrl' => config('portal.url'),
        ];

        return (new MailMessage)
            ->subject('Deine Adressen bei Auri CRM')
            ->view('mail.blocks-message', [
                'brand' => 'Auri CRM',
                'blocks' => $blocks,
            ])
            ->greeting("Hallo {$this->name},")
            ->line($this->inboundAddress !== null
                ? 'die Einrichtung steht. Dein Studio hat dabei zwei eigene Adressen bekommen.'
                : 'die Einrichtung steht. Dein Studio hat dabei eine eigene Adresse bekommen.')
            ->action('Erste Anfrage anlegen', url('/anfragen/neu'))
            ->line('Viele Grüße')
            ->line('Auri CRM');
    }
}
