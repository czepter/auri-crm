<?php

namespace App\Notifications;

use App\Notifications\Contracts\Transport;
use Illuminate\Notifications\Notification;

/**
 * E-07: the single fan-out choke point. Every notification in the system —
 * mail, in-app, and later a webhook transport (I-04) — leaves through here,
 * never through Illuminate\Support\Facades\Notification or a model's own
 * ->notify() called straight from domain code. Domain code names the
 * notifiable and the Notification subclass describing what happened; that
 * subclass's own via() is the only place a channel is picked — the same
 * question ->notify() would ask of it directly — but funnelled through one
 * registry of App\Notifications\Contracts\Transport implementations rather
 * than scattered across every call site.
 *
 * Registered transports live in App\Providers\AppServiceProvider. Adding a
 * channel means adding a Transport to that array, never a new
 * Event::listen() wired straight to a domain event — see Transport's own
 * docblock and issue #68's acceptance criterion.
 */
class NotificationService
{
    /** @param iterable<Transport> $transports */
    public function __construct(private readonly iterable $transports) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $channels = $notification->via($notifiable);

        foreach ($this->transports as $transport) {
            if (in_array($transport->channel(), $channels, true)) {
                $transport->send($notifiable, $notification);
            }
        }
    }
}
