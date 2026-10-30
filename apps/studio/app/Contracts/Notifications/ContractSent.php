<?php

namespace App\Contracts\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Contracts\Models\Contract;
use App\Crm\Concerns\WritesComposedMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Versenden from the compose screen, once per signer. The button only when
 * the project is portal_visible, for QuoteSent's reason: the portal route is
 * a 404 for every other project.
 */
class ContractSent extends Notification
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(
        private readonly Contract $contract,
        private readonly string $subject,
        private readonly ?string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->composedMail($this->subject, $this->body);

        if ($this->contract->case->portal_visible) {
            $message->action('Vertrag ansehen', config('portal.url')."/projekte/{$this->contract->case->public_id}/vertrag");
        }

        return $this->appendStudioMailIdentity($this->appendUnsubscribe($message, $notifiable));
    }
}
