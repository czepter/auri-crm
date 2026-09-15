<?php

namespace App\Automation\Notifications;

use App\Automation\Models\AutomationRule;
use App\Concerns\AppliesStudioMailIdentity;
use App\Documents\Support\MergeFieldResolver;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-08's one action: the mail/in-app content is the rule's own
 * notification_subject/notification_body. E-15 runs both through
 * Documents' merge fields — the same vocabulary and the same resolver a
 * quote or an invoice uses, not a second language — so a studio rule can
 * name which shooting it means. An unknown field is left standing as
 * written: a reminder with a visible gap beats no reminder, and the gap
 * shows in the preview. Not Transactional: an automation
 * reminder is exactly the kind of "status update about something else" mail
 * T-11's quiet hours and preferences exist to hold back, unlike
 * StaffInvited or ClientAccessLink, which the recipient is waiting on right
 * now.
 */
class AutomationReminder extends Notification
{
    use AppliesStudioMailIdentity;

    /** @param array<string, mixed> $data the anchor's project, as DocumentMergeDataBuilder shapes it */
    public function __construct(
        private readonly AutomationRule $rule,
        private readonly StudioSettingsResolver $settings,
        private readonly array $data = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->line($this->body());

        return $this->appendStudioMailIdentity($message, $this->settings);
    }

    public function subject(): string
    {
        return MergeFieldResolver::resolve($this->rule->notification_subject, $this->data, keepUnknown: true);
    }

    public function body(): string
    {
        return MergeFieldResolver::resolve($this->rule->notification_body, $this->data, keepUnknown: true);
    }

    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject(), 'body' => $this->body()];
    }
}
