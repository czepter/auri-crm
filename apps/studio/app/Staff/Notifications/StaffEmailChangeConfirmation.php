<?php

namespace App\Staff\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Models\User;
use App\Models\UserEmailChange;
use App\Notifications\Contracts\Transactional;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the NEW address and nowhere else — following it is the proof that
 * the address exists and belongs to whoever asked. The portal's own
 * App\Portal\Notifications\EmailChangeConfirmation is the same mail one
 * register over; staff are addressed with "du", clients with "Sie", and the
 * copy is the reason the two are not one class.
 */
class StaffEmailChangeConfirmation extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly User $user,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Neue E-Mail-Adresse bestätigen')
            ->greeting("Hallo {$this->user->name},")
            ->action('Adresse bestätigen', $this->url)
            ->line('Der Link gilt '.UserEmailChange::VALID_FOR_HOURS.' Stunden.')
            ->line('Bis dahin meldest du dich mit der bisherigen Adresse an.');

        return $this->appendStudioMailIdentity($message);
    }
}
