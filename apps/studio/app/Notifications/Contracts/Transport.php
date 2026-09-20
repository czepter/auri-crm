<?php

namespace App\Notifications\Contracts;

use Illuminate\Notifications\Notification;

/**
 * One channel a notification can be delivered through. E-07: App\
 * Notifications\NotificationService is the only thing that ever calls
 * send() — a Transport never subscribes to a domain event on its own, so
 * there is exactly one fan-out path to look at when nobody got notified.
 * Registering a new channel means
 * writing one of these and adding it to the service's registry, never
 * wiring a new Event::listen() straight to the transport itself.
 */
interface Transport
{
    /** The channel name a Notification::via() lists to reach this transport. */
    public function channel(): string;

    public function send(mixed $notifiable, Notification $notification): void;
}
