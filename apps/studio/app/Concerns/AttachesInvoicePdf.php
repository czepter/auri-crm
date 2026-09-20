<?php

namespace App\Concerns;

use App\Payments\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * #415: the invoice PDF on a client mail. Skipped where the render has not
 * landed, so the mail goes out with its message and its link rather than not
 * at all.
 */
trait AttachesInvoicePdf
{
    protected function attachInvoicePdf(MailMessage $message, ?Invoice $invoice): MailMessage
    {
        $path = $invoice?->pdf_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return $message;
        }

        return $message->attachData(
            Storage::disk('local')->get($path),
            "rechnung-{$invoice->number}.pdf",
            ['mime' => 'application/pdf'],
        );
    }
}
