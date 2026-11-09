<?php

namespace App\Payments\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Crm\Models\Contact;
use App\Crm\Support\CaseMergeData;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MailDefaults;
use App\Payments\Concerns\AttachesInvoicePdf;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * T-25: the mail that actually settles a rate, as opposed to the internal
 * one that only creates the job of writing it.
 *
 * It says Erinnerung and it means Erinnerung. "Verzug" is a legal term with
 * conditions this software does not check, so it stays in the studio's own
 * message, where it sorts a list rather than making a claim to the reader.
 */
class PaymentReminder extends Notification
{
    use AppliesStudioMailIdentity;
    use AttachesInvoicePdf;
    use WritesComposedMail;

    public function __construct(
        private readonly PaymentInstallment $installment,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->installment->plan->case;

        $amount = Number::currency($this->installment->remaining() / 100, in: 'EUR', locale: 'de');
        $dueOn = $this->installment->due_on->format('d.m.Y');
        $data = [
            ...app(DocumentMergeData::class)->forCase($project),
            'contact' => CaseMergeData::contact($notifiable instanceof Contact ? $notifiable : $project->primaryContact()),
            // #414: der offene Rest, nicht der Ratenbetrag — eine angezahlte
            // Rate wird sonst über einen Betrag angemahnt, der schon kleiner ist.
            'reminder' => [
                'amount' => $amount,
                'dueOn' => $dueOn,
                'invoiceNumber' => $this->installment->invoice?->number ?? '',
            ],
        ];

        $default = MailDefaults::resolve('payment_reminder', $data);
        $subject = $default['subject'] ?? "Offene Rate zu {$project->name}";

        $message = $default !== null
            ? $this->composedMail($subject, $default['message'])
            : (new MailMessage)
                ->subject($subject)
                ->greeting('Hallo,')
                ->line("Die Rate über {$amount} war am {$dueOn} fällig.");

        $message
            // The portal is where "Jetzt zahlen" lives when Stripe is on, and
            // where the bank details stand when it is not — either way the
            // client lands on the one screen that closes this.
            ->action('Zahlung ansehen', config('portal.url')."/projekte/{$project->public_id}/zahlungen");

        // #415: die Rechnung liegt bei, damit die Erinnerung ohne Portal-Login
        // alles enthält, was zum Bezahlen gebraucht wird.
        $this->attachInvoicePdf($message, $this->installment->invoice);

        if ($notifiable instanceof Contact) {
            $message->line(
                '[Benachrichtigungen abbestellen]('
                .config('portal.url')."/abmelden/{$notifiable->public_id}".')'
            );
        }

        return $this->appendStudioMailIdentity($message);
    }
}
