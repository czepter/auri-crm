<?php

namespace App\Payments\Jobs;

use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Models\PaymentInstallment;
use App\Support\GotenbergClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Storage and the guards; the document itself is RenderInvoicePdf's, since
 * #419 moved the § 14 UStG particulars out of the template and into the
 * studio's chosen layout.
 *
 * Guards on the artifact rather than on a status column. An invoice that
 * already has a PDF is never re-rendered — the client may already hold it,
 * and IssueInvoice pinned the template version precisely so a re-run could
 * not quietly produce different wording. An invoice that has none can be
 * re-run as often as it takes.
 */
class RenderInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly PaymentInstallment $installment) {}

    public function handle(GotenbergClient $gotenberg, RenderInvoicePdf $render): void
    {
        $installment = $this->installment->fresh(['invoiceTemplate', 'plan.job.project']);

        if ($installment === null || ! $installment->isInvoiced() || $installment->invoice_path !== null) {
            return;
        }

        // #408: a Storno that beat the render leaves nothing worth producing —
        // the number is retired and no client is waiting for the document.
        if ($installment->isCancelled()) {
            return;
        }

        if ($installment->plan?->job?->project === null) {
            return;
        }

        $pdf = $gotenberg->htmlToPdf($render->forInstallment($installment));

        $path = "invoices/{$installment->tenant_id}/{$installment->public_id}.pdf";
        Storage::disk('local')->put($path, $pdf);

        $installment->update(['invoice_path' => $path]);
    }
}
