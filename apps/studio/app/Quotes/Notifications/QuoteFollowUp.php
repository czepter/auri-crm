<?php

namespace App\Quotes\Notifications;

use App\Crm\Models\Contact;
use App\Documents\Support\MailDefaults;
use App\Models\StudioSettings;
use App\Quotes\Models\Quote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A nudge about a quote that went out and has had no answer. Not Transactional: the unsubscribe applies. */
class QuoteFollowUp extends Notification
{
    public function __construct(
        private readonly Quote $quote,
        private readonly string $customSubject,
        private readonly string $customMessage,
    ) {}

    /** @return array{subject: string, message: string} */
    public static function defaults(Quote $quote): array
    {
        $case = $quote->case;
        $address = StudioSettings::current()->clientAddress($case->contacts()->count());
        $name = $case->primaryContact()?->name;
        $since = $quote->sent_at?->format('d.m.Y');

        $subject = [
            'sie' => "Ihr Angebot {$quote->number}",
            'du' => "Dein Angebot {$quote->number}",
            'ihr' => "Euer Angebot {$quote->number}",
        ][$address];

        $body = [
            'sie' => "das Angebot {$quote->number} liegt seit {$since} bei Ihnen. Gibt es Fragen oder Änderungswünsche? Ich passe es gern an.",
            'du' => "das Angebot {$quote->number} liegt seit {$since} bei dir. Gibt es Fragen oder Änderungswünsche? Ich passe es gern an.",
            'ihr' => "das Angebot {$quote->number} liegt seit {$since} bei euch. Gibt es Fragen oder Änderungswünsche? Ich passe es gern an.",
        ][$address];

        $default = MailDefaults::resolve('quote_follow_up', QuoteSent::mergeData($quote));

        return [
            'subject' => $default['subject'] ?? $subject,
            'message' => $default['message'] ?? 'Hallo'.($name ? " {$name}" : '').",\n\n{$body}",
        ];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->customSubject);

        foreach (preg_split('/\n{2,}/', trim($this->customMessage)) as $paragraph) {
            $message->line($paragraph);
        }

        if ($this->quote->case->portal_visible) {
            $message->action('Angebot ansehen', config('portal.url')."/projekte/{$this->quote->case->public_id}/angebot");
        }

        if ($notifiable instanceof Contact) {
            $message->line('[Benachrichtigungen abbestellen]('.config('portal.url')."/abmelden/{$notifiable->public_id}".')');
        }

        return $message;
    }
}
