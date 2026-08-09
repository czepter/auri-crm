<?php

namespace App\Notifications\Transports;

use App\Notifications\Contracts\Transport;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Delegates to Laravel's own mail channel rather than re-rendering a
 * MailMessage by hand. sendNow(..., ['mail']) is exactly what
 * Notification::route(...)->notify(...) already did at every call site
 * NotificationService now replaces, restricted to the one channel this
 * transport owns — so Notification::fake()'s assertSentOnDemand() in
 * tests/Feature/Staff/InvitationTest.php and tests/Feature/Portal/
 * MagicLinkTest.php keeps working unchanged underneath the new choke point.
 */
class MailTransport implements Transport
{
    public function channel(): string
    {
        return 'mail';
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        NotificationFacade::sendNow($notifiable, $notification, ['mail']);
    }
}
