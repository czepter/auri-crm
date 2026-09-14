<?php

namespace App\Notifications\Support;

use Illuminate\Notifications\Notification;

/**
 * The compose screens show the notification's own render rather than a
 * rebuilt one, so the lines, button and footer a mail class adds cannot
 * drift from what the client receives.
 */
final class MailPreview
{
    /** Stands in for the message; SendByMailView.vue replaces its paragraph with the one being typed. */
    public const MESSAGE = '%%NACHRICHT%%';

    public static function html(Notification $notification, object $notifiable): string
    {
        return (string) $notification->toMail($notifiable)->render();
    }
}
