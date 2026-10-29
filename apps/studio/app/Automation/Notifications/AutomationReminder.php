<?php

namespace App\Automation\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-08's mail: a rule's subject and body, already resolved against the
 * anchor's project by FireAutomationRule — or, for a client draft, what the
 * studio edited on the compose screen. Not Transactional: an automation
 * reminder is exactly the kind of "status update about something else" mail
 * T-11's quiet hours and preferences exist to hold back.
 */
class AutomationReminder extends Notification
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly string $subject,
        private readonly string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);

        foreach (preg_split('/\n{2,}/', trim($this->body)) as $paragraph) {
            $message->line($paragraph);
        }

        return $this->appendStudioMailIdentity($message);
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function toArray(object $notifiable): array
    {
        return ['subject' => $this->subject, 'body' => $this->body];
    }
}
