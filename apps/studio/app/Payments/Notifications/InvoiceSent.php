<?php

namespace App\Payments\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Payments\Concerns\AttachesInvoicePdf;
use App\Payments\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * #415: subject and paragraphs come from the compose
 * screen, and this class adds only what the screen cannot.
 *
 * The PDF travels with the mail. A § 14 UStG invoice is a document the client
 * keeps, and a link into a portal they may never sign into is not delivery of
 * it. The button is the portal page all the same, and only where the project
 * is portal_visible — the route is a 404 for every other one.
 */
class InvoiceSent extends Notification
{
    use AppliesStudioMailIdentity;
    use AttachesInvoicePdf;
    use WritesComposedMail;

    public function __construct(
        private readonly Invoice $invoice,
        private readonly string $subject,
        private readonly ?string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** The compose screen's starting text, and what the create screen's preview shows in its place. */
    public static function defaultMessage(Invoice $invoice): string
    {
        $name = $invoice->contact?->name;

        return ($name === null ? 'Guten Tag,' : "Hallo {$name},")."\n\nanbei die Rechnung {$invoice->number}.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->composedMail($this->subject, $this->body);
        $project = $this->invoice->case;
        $dueOn = $this->invoice->dueOn();

        // What the attachment says, readable without opening it.
        $message->line(sprintf(
            'Rechnungsbetrag %s%s.',
            Number::currency($this->invoice->amount() / 100, in: 'EUR', locale: 'de'),
            $dueOn === null ? '' : ', zahlbar bis '.$dueOn->format('d.m.Y'),
        ));

        if ($project?->portal_visible) {
            $message->action(
                'Rechnung ansehen',
                config('portal.url')."/projekte/{$project->public_id}/zahlungen/{$this->invoice->public_id}",
            );
        }

        $this->attachInvoicePdf($message, $this->invoice);

        // No unsubscribe: an invoice is transactional, not a notification a client can opt out of.
        return $this->appendStudioMailIdentity($message);
    }
}
