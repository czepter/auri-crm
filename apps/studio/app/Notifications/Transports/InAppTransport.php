<?php

namespace App\Notifications\Transports;

use App\Notifications\Contracts\Transport;
use App\Notifications\Models\InAppNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Writes one row per send to in_app_notifications, keyed to the notifiable
 * by its own polymorphic type/id — see the migration's docblock. Only a
 * real, persisted Eloquent model can receive one: there is no inbox to
 * write into for an on-demand address (Notification::route('mail', $email)),
 * the shape every mail-only send in this codebase uses today.
 */
class InAppTransport implements Transport
{
    public function channel(): string
    {
        return 'in_app';
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof Model || ! $notifiable->exists) {
            throw new RuntimeException(
                'InAppTransport needs a persisted Eloquent model to notify — got '.get_debug_type($notifiable).'.'
            );
        }

        InAppNotification::create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'type' => $notification::class,
            'data' => $notification->toArray($notifiable),
        ]);
    }
}
