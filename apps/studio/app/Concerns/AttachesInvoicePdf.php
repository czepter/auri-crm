<?php

namespace App\Concerns;

use App\Payments\Models\PaymentInstallment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * #415: the invoice PDF on a client mail. Skipped where the render has not
 * landed, so the mail goes out with its message and its link rather than not
 * at all.
 */
trait AttachesInvoicePdf
{
    protected function attachInvoicePdf(MailMessage $message, PaymentInstallment $installment): MailMessage
    {
        $path = $installment->invoice_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return $message;
        }

        return $message->attachData(
            Storage::disk('local')->get($path),
            "rechnung-{$installment->invoice_number}.pdf",
            ['mime' => 'application/pdf'],
        );
    }
}
