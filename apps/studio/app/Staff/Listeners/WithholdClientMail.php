<?php

namespace App\Staff\Listeners;

use App\Models\StudioSettings;
use App\Models\User;
use App\Notifications\Contracts\IgnoresClientMailSwitch;
use App\Support\AuditLogger;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * On the framework's NotificationSending rather than in NotificationService:
 * PublishGallery calls ->notify() directly, and the gallery and portal code
 * mails dispatch SendMailNotification themselves.
 *
 * Everyone who is not staff counts as a client, so an on-demand staff mail
 * stays withheld unless it implements IgnoresClientMailSwitch.
 */
class WithholdClientMail
{
    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel !== 'mail'
            || $event->notifiable instanceof User
            || $event->notification instanceof IgnoresClientMailSwitch
            || (bool) StudioSettings::current()->mail_clients_enabled) {
            return null;
        }

        AuditLogger::record(
            event: 'notification.withheld',
            context: ['notification' => $event->notification::class],
        );

        return false;
    }
}
