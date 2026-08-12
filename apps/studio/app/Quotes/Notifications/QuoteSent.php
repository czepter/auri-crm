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
 * Links to Q-07's portal quote screen, but only when the studio has made the
 * project portal_visible: the portal route is a 404 for every other project,
 * and a button into a 404 is worse than no button. Signing in is the portal's
 * own problem from there — the link lands on the magic-link screen when the
 * contact has no session, same as any other portal URL.
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
            ->line('Gesamtbetrag: '.Number::currency($this->quote->net / 100, in: 'EUR', locale: 'de'));

        if ($this->quote->valid_until !== null) {
            $message->line("Das Angebot ist gültig bis {$this->quote->valid_until->format('d.m.Y')}.");
        }

        // Raw url() for the same reason the unsubscribe line below uses one.
        if ($this->quote->project->portal_visible) {
            $message->action(
                'Angebot ansehen',
                url("/projekte/{$this->quote->project->public_id}/angebot")
            );
        }

        // T-11: the unsubscribe every non-transactional mail must carry.
        // Raw url() rather than route('portal.unsubscribe.show', ...) — this
        // notification is sent from the admin surface's process, which never
        // registers the portal's own route table (see Surface::registerRoutes()
        // and how ClientAccessLink/StaffInvited build their own links the
        // same way, one surface over).
        if ($notifiable instanceof Contact) {
            $message->line(
                '[Benachrichtigungen abbestellen]('
                .url("/abmelden/{$notifiable->public_id}").')'
            );
        }

        return $message;
    }
}
