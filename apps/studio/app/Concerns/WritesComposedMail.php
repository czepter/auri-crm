<?php

namespace App\Concerns;

use App\Crm\Models\Contact;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mails behind SendByMailView.vue: subject and paragraphs come from the
 * compose screen, so the class only adds what the screen cannot (its button,
 * the unsubscribe line).
 */
trait WritesComposedMail
{
    /** The compose screen posts its paragraphs as plain text, blank-line separated. */
    protected function composedMail(string $subject, ?string $body): MailMessage
    {
        $message = (new MailMessage)->subject($subject);

        foreach (preg_split('/\n{2,}/', trim((string) $body)) as $paragraph) {
            if ($paragraph !== '') {
                $message->line($paragraph);
            }
        }

        return $message;
    }

    /**
     * T-11: the unsubscribe every non-transactional client mail carries. A
     * literal portal path, as in QuoteSent: the admin process never registers
     * the portal's route table.
     */
    protected function appendUnsubscribe(MailMessage $message, object $notifiable): MailMessage
    {
        if ($notifiable instanceof Contact) {
            $message->line('[Benachrichtigungen abbestellen]('.config('portal.url')."/abmelden/{$notifiable->public_id}".')');
        }

        return $message;
    }
}
