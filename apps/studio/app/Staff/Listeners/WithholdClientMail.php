<?php

namespace App\Staff\Listeners;

use App\Gallery\Notifications\GalleryEmailVerificationLink;
use App\Gallery\Notifications\GalleryGuestCode;
use App\Models\StudioSettings;
use App\Models\User;
use App\Portal\Notifications\ClientAccessLink;
use App\Staff\Notifications\StaffEmailChangeConfirmation;
use App\Staff\Notifications\StaffInvited;
use App\Support\AuditLogger;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * On the framework's NotificationSending rather than in NotificationService:
 * PublishGallery calls ->notify() directly, and the gallery and portal code
 * mails dispatch SendMailNotification themselves.
 *
 * Everyone who is not staff counts as a client, so an on-demand staff mail
 * added later stays withheld until it is listed here. Login mails pass: the
 * client asked for them, and without them portal and gallery stay locked.
 */
class WithholdClientMail
{
    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel !== 'mail'
            || $event->notifiable instanceof User
            || $event->notification instanceof StaffInvited
            || $event->notification instanceof StaffEmailChangeConfirmation
            || $event->notification instanceof ClientAccessLink
            || $event->notification instanceof GalleryGuestCode
            || $event->notification instanceof GalleryEmailVerificationLink
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
