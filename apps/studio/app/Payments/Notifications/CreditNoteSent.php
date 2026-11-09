<?php

namespace App\Payments\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Crm\Concerns\WritesComposedMail;
use App\Documents\Support\MergeFieldResolver;
use App\Models\StudioSettings;
use App\Payments\Models\CreditNote;
use App\Payments\Support\InvoiceMergeData;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The Gutschrift to the client who paid the invoice it takes back. Transactional
 * like the invoice itself: a tax document, not a notification to opt out of.
 */
class CreditNoteSent extends Notification
{
    use AppliesStudioMailIdentity;
    use WritesComposedMail;

    public function __construct(private readonly CreditNote $creditNote) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = app(InvoiceMergeData::class)->forInvoice($this->creditNote->invoice, $this->creditNote);
        $settings = StudioSettings::current();

        $subject = filled($settings->credit_note_mail_subject)
            ? MergeFieldResolver::resolve($settings->credit_note_mail_subject, $data)
            : "Gutschrift {$this->creditNote->number}";
        $body = filled($settings->credit_note_mail_body)
            ? MergeFieldResolver::resolve($settings->credit_note_mail_body, $data)
            : "Hallo {$data['contact']['name']},\n\nanbei die Gutschrift {$this->creditNote->number} zur Rechnung {$this->creditNote->invoice->number}.";

        $message = $this->composedMail($subject, $body);

        $path = $this->creditNote->pdf_path;

        if ($path !== null && Storage::disk('local')->exists($path)) {
            $message->attachData(Storage::disk('local')->get($path), "gutschrift-{$this->creditNote->number}.pdf", ['mime' => 'application/pdf']);
        }

        return $this->appendStudioMailIdentity($message);
    }
}
