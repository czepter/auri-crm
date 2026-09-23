<?php

namespace App\Payments\Jobs;

use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Models\Invoice;
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

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(GotenbergClient $gotenberg, RenderInvoicePdf $render): void
    {
        $invoice = $this->invoice->fresh(['template', 'project', 'contact', 'installments.plan.project', 'items']);

        if ($invoice === null) {
            return;
        }

        // The file rather than the recorded path: a row whose PDF is gone is
        // exactly the re-render #416's snapshot exists for, and one whose PDF
        // is there must never be produced a second time. Invoices moved from
        // installments (#480) keep the path they were rendered under.
        $path = $invoice->pdf_path ?? "invoices/{$invoice->tenant_id}/{$invoice->public_id}.pdf";

        if (Storage::disk('local')->exists($path)) {
            return;
        }

        // #408: a Storno that beat the render leaves nothing worth producing —
        // the number is retired and no client is waiting for the document.
        if ($invoice->isCancelled()) {
            return;
        }

        $pdf = $gotenberg->htmlToPdf($render->forInvoice($invoice, snapshotPath: $path));

        Storage::disk('local')->put($path, $pdf);

        $invoice->update(['pdf_path' => $path]);
    }
}
