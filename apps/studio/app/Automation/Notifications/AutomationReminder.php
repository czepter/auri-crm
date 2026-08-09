<?php

namespace App\Automation\Notifications;

use App\Automation\Models\AutomationRule;
use App\Concerns\AppliesStudioMailIdentity;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-08's one action: the mail/in-app content is entirely the rule's own
 * notification_subject/notification_body — kept as plain text rather than a
 * template language, since proving the engine fires is this story's job,
 * not building a merge-field system. Not Transactional: an automation
 * reminder is exactly the kind of "status update about something else" mail
 * T-11's quiet hours and preferences exist to hold back, unlike
 * StaffInvited or ClientAccessLink, which the recipient is waiting on right
 * now.
 */
class AutomationReminder extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly AutomationRule $rule,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->rule->notification_subject)
            ->line($this->rule->notification_body);

        return $this->appendStudioMailIdentity($message, $this->settings);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->rule->notification_subject,
            'body' => $this->rule->notification_body,
        ];
    }
}
