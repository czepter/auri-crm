<?php

namespace App\Quotes\Notifications;

use App\Crm\Models\Contact;
use App\Quotes\Models\Quote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Tells a client a quote is on its way. Sent to the Contact itself — Contact
 * gained the Notifiable trait in T-11 precisely so a client-facing send can
 * carry a real identity rather than an on-demand address, which is what lets
 * T-11's preferences apply to it at all.
 *
 * Never queued: App\Quotes\Actions\SendQuote sends this in the same request
 * that flips the quote to sent, per the issue's own reasoning that a single
 * quote email is not worth the queue M-00 shipped.
 *
 * No link to a portal quote screen — that screen is Q-07's, and does not
 * exist yet.
 *
 * T-11: NOT Transactional — a quote is business content about something that
 * already happened elsewhere (SendQuote's own DB write), not a credential
 * the client is waiting on this instant, so both a preference and the
 * one-click unsubscribe this mail's footer links to can hold it.
 */
class QuoteSent extends Notification
{
    public function __construct(private readonly Quote $quote) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Ihr Angebot {$this->quote->number}")
            ->greeting('Hallo,')
            ->line("wir haben Ihnen ein neues Angebot ({$this->quote->number}) zugestellt.")
            ->line('Gesamtbetrag: '.Number::currency($this->quote->net / 100, in: 'EUR', locale: 'de'));

        if ($this->quote->valid_until !== null) {
            $message->line("Das Angebot ist gültig bis {$this->quote->valid_until->format('d.m.Y')}.");
        }

        $message->line('Bei Fragen melden Sie sich gerne bei uns.');

        // T-11: the unsubscribe every non-transactional mail must carry.
        // Raw url() rather than route('portal.unsubscribe.show', ...) — this
        // notification is sent from the admin surface's process, which never
        // registers the portal's own route table (see Surface::registerRoutes()
        // and how ClientAccessLink/StaffInvited build their own links the
        // same way, one surface over).
        if ($notifiable instanceof Contact) {
            $message->line(
                'Diese Mail ist keine Pflichtmitteilung. [Abmeldung verwalten]('
                .url("/abmelden/{$notifiable->public_id}").')'
            );
        }

        return $message;
    }
}
