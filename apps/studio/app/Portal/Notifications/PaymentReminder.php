<?php

namespace App\Portal\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Concerns\AttachesInvoicePdf;
use App\Crm\Models\Contact;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
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

    public function __construct(
        private readonly PaymentInstallment $installment,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->installment->plan->job->project;

        $message = (new MailMessage)
            ->subject("Offene Rate zu {$project->name}")
            ->greeting('Hallo,')
            // #414: der offene Rest, nicht der Ratenbetrag — eine angezahlte
            // Rate wird sonst über einen Betrag angemahnt, der schon kleiner ist.
            ->line(sprintf(
                'Die Rate über %s war am %s fällig.',
                Number::currency($this->installment->remaining() / 100, in: 'EUR', locale: 'de'),
                $this->installment->due_on->format('d.m.Y'),
            ))
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

        return $this->appendStudioMailIdentity($message, $this->settings);
    }
}
