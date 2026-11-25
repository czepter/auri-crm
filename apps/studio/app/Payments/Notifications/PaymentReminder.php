<?php

namespace App\Payments\Notifications;

use App\Crm\Models\Contact;
use App\Crm\Support\CaseMergeData;
use App\Documents\Support\DocumentMergeData;
use App\Documents\Support\MailDefaults;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Number;

/**
 * T-25: the text of the reminder for an open rate. It never goes out on its
 * own: DetectOverdueInstallments files it as a draft in the Posteingang.
 *
 * It says Erinnerung and it means Erinnerung. "Verzug" is a legal term with
 * conditions this software does not check, so it stays in the studio's own
 * message, where it sorts a list rather than making a claim to the reader.
 */
class PaymentReminder
{
    public function __construct(
        private readonly PaymentInstallment $installment,
    ) {}

    /** @return array{subject: string, body: string} */
    public function draft(Contact $contact): array
    {
        $project = $this->installment->plan->case;

        $amount = Number::currency($this->installment->remaining() / 100, in: 'EUR', locale: 'de');
        $dueOn = $this->installment->due_on->format('d.m.Y');
        $data = [
            ...app(DocumentMergeData::class)->forCase($project),
            'contact' => CaseMergeData::contact($contact),
            // #414: der offene Rest, nicht der Ratenbetrag — eine angezahlte
            // Rate wird sonst über einen Betrag angemahnt, der schon kleiner ist.
            'reminder' => [
                'amount' => $amount,
                'dueOn' => $dueOn,
                'invoiceNumber' => $this->installment->invoice?->number ?? '',
            ],
        ];

        $default = MailDefaults::resolve('payment_reminder', $data);

        return [
            'subject' => $default['subject'] ?? "Offene Rate zu {$project->name}",
            'body' => ($default['message'] ?? "Hallo,\n\nDie Rate über {$amount} war am {$dueOn} fällig.")
                // Where "Jetzt zahlen" lives with Stripe on, and the bank details without.
                ."\n\n".config('portal.url')."/projekte/{$project->public_id}/zahlungen",
        ];
    }
}
