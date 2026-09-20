<?php

namespace App\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Every outgoing mail, on the `mail` queue. MailTransport used to hand the
 * message to the MTA inside the request that triggered it, which put an SMTP
 * round trip in front of the response and made the sending container's own
 * mail configuration part of whether a lead was ever heard about — a
 * public inquiry reaching the api surface died there, because only the
 * containers that run workers had a DSN.
 *
 * Calls the mail channel directly rather than re-entering
 * NotificationService::send(): the fan-out already happened when this was
 * dispatched, and going back through it would write the in-app row a second
 * time.
 */
class SendMailNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly mixed $notifiable,
        public readonly Notification $notification,
    ) {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        NotificationFacade::sendNow($this->notifiable, $this->notification, ['mail']);
    }
}
