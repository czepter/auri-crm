<?php

namespace App\Engagement\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Engagement\Models\Message;
use App\Notifications\Contracts\Transactional;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * E-11: the staff reply, as mail.
 *
 * Until this existed a reply sat in the portal and a client who never logged
 * in never learned there was an answer. The Message row is still the record;
 * this is how it reaches the person it was written to.
 *
 * **Transactional.** A client waiting on an answer to their own question is
 * not receiving marketing, and holding it for quiet hours would mean an
 * answer written at 21:00 arriving the next morning while the studio believes
 * it is sent.
 *
 * The Message-ID is set here rather than left to the mailer, and it is
 * derived from the message's own `public_id`: the row must know the id of
 * the mail that carried it *before* the send, or E-12 has nothing to thread
 * an inbound reply against. A mailer-assigned id would be knowable only
 * afterwards, and only by listening for an event that does not fire on every
 * transport.
 */
class StaffReplyPosted extends Notification implements Transactional
{
    use AppliesStudioMailIdentity;

    public function __construct(
        private readonly Message $message,
        private readonly StudioSettingsResolver $settings,
        /** E-12's `t.<thread>.<hmac>@…` address, once that story lands. Null until then. */
        private readonly ?string $replyTo = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message->thread->subject)
            ->line($this->message->body);

        $mail = $this->appendStudioMailIdentity($mail, $this->settings);

        // After the identity trait, so a thread-scoped reply address wins
        // over the studio's general one — a reply to *this* conversation
        // should thread, not land in the shared inbox.
        if ($this->replyTo !== null) {
            $mail->replyTo($this->replyTo);
        }

        $messageId = $this->message->email_message_id;

        return $mail->withSymfonyMessage(function (Email $email) use ($messageId) {
            if ($messageId !== null) {
                $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
            }
        });
    }
}
