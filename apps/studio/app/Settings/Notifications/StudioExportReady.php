<?php

namespace App\Settings\Notifications;

use App\Notifications\Contracts\Transactional;
use Illuminate\Notifications\Notification;

class StudioExportReady extends Notification implements Transactional
{
    public function __construct(private readonly string $label) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['in_app'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Export bereit',
            'body' => $this->label,
            'url' => '/einstellungen/export',
        ];
    }
}
