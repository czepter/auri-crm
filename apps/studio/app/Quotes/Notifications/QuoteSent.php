<?php

namespace App\Quotes\Notifications;

use App\Crm\Models\Contact;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
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
    /**
     * $customSubject and $customMessage: the compose screen's own fields —
     * both null on every other caller (markSent's own direct send, the
     * tests below), which keeps this exactly the mail it was before.
     */
    public function __construct(
        private readonly Quote $quote,
        private readonly ?string $customSubject = null,
        private readonly ?string $customMessage = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $possessive = [
            'sie' => 'Ihr',
            'du' => 'Dein',
            'ihr' => 'Euer',
        ][app(StudioSettingsResolver::class)->clientAddress($this->quote->project->contacts()->count())];

        $message = (new MailMessage)->subject($this->customSubject ?: "{$possessive} Angebot {$this->quote->number}");

        if ($this->customMessage !== null && trim($this->customMessage) !== '') {
            // The compose screen's paragraphs, blank-line separated —
            // already the studio's own greeting, so MailMessage's own
            // ->greeting() would double it.
            foreach (preg_split('/\n{2,}/', trim($this->customMessage)) as $paragraph) {
                $message->line($paragraph);
            }
        } else {
            $message->greeting('Hallo,');
        }

        // The same figure QuoteWorkspace's own summaryRows shows: Rabatt only
        // when there is one, USt. only for a regelbesteuertes Studio — a
        // Kleinunternehmer's net already is the amount due.
        if ($this->quote->discount_amount > 0 && ! $this->quote->discount_hidden) {
            $message
                ->line('Zwischensumme: '.Number::currency($this->quote->total / 100, in: 'EUR', locale: 'de'))
                ->line('Rabatt: −'.Number::currency($this->quote->discount_amount / 100, in: 'EUR', locale: 'de'));
        }

        if ($this->quote->vat_mode === 'standard') {
            $rate = rtrim(rtrim(number_format($this->quote->vat_rate / 100, 2, ',', '.'), '0'), ',');

            $message
                ->line('Netto: '.Number::currency($this->quote->net / 100, in: 'EUR', locale: 'de'))
                ->line("USt. {$rate} %: ".Number::currency(($this->quote->vat_amount ?? 0) / 100, in: 'EUR', locale: 'de'))
                ->line('Gesamtbetrag: '.Number::currency(($this->quote->net + ($this->quote->vat_amount ?? 0)) / 100, in: 'EUR', locale: 'de'));
        } else {
            $message->line('Gesamtbetrag: '.Number::currency($this->quote->net / 100, in: 'EUR', locale: 'de'));
        }

        if ($this->quote->valid_until !== null) {
            $message->line("Das Angebot ist gültig bis {$this->quote->valid_until->format('d.m.Y')}.");
        }

        // Both links below are portal paths, so they compose against
        // config('portal.url') and never url(): SendQuote runs on the admin
        // surface, and a quiet-hours DeferredNotification re-renders this on
        // the worker, which is admin too. url() would put the studio's host
        // in front of a route only the portal serves.
        if ($this->quote->project->portal_visible) {
            $message->action(
                'Angebot ansehen',
                config('portal.url')."/projekte/{$this->quote->project->public_id}/angebot"
            );
        }

        // T-11: the unsubscribe every non-transactional mail must carry.
        // A literal path rather than route('portal.unsubscribe.show', ...):
        // the admin process never registers the portal's route table at all
        // (Surface::registerRoutes()).
        if ($notifiable instanceof Contact) {
            $message->line(
                '[Benachrichtigungen abbestellen]('
                .config('portal.url')."/abmelden/{$notifiable->public_id}".')'
            );
        }

        return $message;
    }
}
