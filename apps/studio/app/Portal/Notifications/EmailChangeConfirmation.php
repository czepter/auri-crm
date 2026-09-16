<?php

namespace App\Portal\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Models\Contact;
use App\Notifications\Contracts\Transactional;
use App\Portal\Models\EmailChange;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the NEW address and nowhere else — following it is the proof that
 * the address exists and belongs to whoever asked. Transactional for the
 * same reason the magic link is: the client is waiting on it, and a client
 * mid-way through changing their login is not someone to hold in a
 * quiet-hours queue.
 */
class EmailChangeConfirmation extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Contact $contact,
        private readonly string $url,
        private readonly StudioSettingsResolver $settings,
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
            ->greeting("Hallo {$this->contact->name},")
            ->action('Adresse bestätigen', $this->url)
            ->line('Der Link gilt '.EmailChange::VALID_FOR_HOURS.' Stunden.')
            ->line($this->settings->clientAddress() === 'du'
                ? 'Bis dahin bleibt die bisherige Adresse dein Zugang.'
                : 'Bis dahin bleibt die bisherige Adresse Ihr Zugang.');

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
