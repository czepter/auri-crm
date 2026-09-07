<?php

namespace App\Notifications\Transports;

use App\Notifications\Contracts\Transport;
use App\Notifications\Jobs\SendMailNotification;
use Illuminate\Notifications\Notification;

/**
 * Queued, never inline: SendMailNotification carries the send on the `mail`
 * queue, so the SMTP round trip is never inside the request that triggered
 * it and a mail outlives a failed connection as a retryable job instead of
 * a 500. It is the job that calls Laravel's own mail channel, restricted to
 * the one channel this transport owns — Notification::fake()'s
 * assertSentOnDemand() in tests/Feature/Staff/InvitationTest.php and
 * tests/Feature/Portal/MagicLinkTest.php still sees the send underneath,
 * because the test suite runs QUEUE_CONNECTION=sync.
 *
 * Nothing here honours ShouldQueue on the notification itself: a
 * notification that names the mail channel is queued because it is mail,
 * not because it remembered to say so.
 */
class MailTransport implements Transport
{
    public function channel(): string
    {
        return 'mail';
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        SendMailNotification::dispatch($notifiable, $notification);
    }
}
