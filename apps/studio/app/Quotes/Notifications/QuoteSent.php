<?php

namespace App\Quotes\Notifications;

use App\Quotes\Models\Quote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Tells a client a quote is on its way. Follows App\Portal\Notifications\
 * ClientAccessLink's shape — a plain Notification, on-demand-routed to an
 * email address rather than to a Notifiable model, since Contact carries no
 * Notifiable trait — and its German-language conventions.
 *
 * Never queued: App\Quotes\Actions\SendQuote calls ->notify() directly, in
 * the same request that flips the quote to sent, per the issue's own
 * reasoning that a single quote email is not worth the queue M-00 shipped.
 *
 * No link to a portal quote screen — that screen is Q-07's, and does not
 * exist yet.
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

        return $message->line('Bei Fragen melden Sie sich gerne bei uns.');
    }
}
