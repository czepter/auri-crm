<?php

namespace App\Payments\Notifications;

use App\Concerns\AppliesStudioMailIdentity;
use App\Concerns\AttachesInvoicePdf;
use App\Concerns\WritesComposedMail;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        private readonly PaymentInstallment $installment,
        private readonly string $subject,
        private readonly ?string $body,
        private readonly StudioSettingsResolver $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->composedMail($this->subject, $this->body);
        $project = $this->installment->plan->job->project;

        if ($project->portal_visible) {
            $message->action(
                'Rechnung ansehen',
                config('portal.url')."/projekte/{$project->public_id}/zahlungen/{$this->installment->public_id}",
            );
        }

        $this->attachInvoicePdf($message, $this->installment);

        return $this->appendStudioMailIdentity($this->appendUnsubscribe($message, $notifiable), $this->settings);
    }
}
