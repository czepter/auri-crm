<?php

namespace App\Payments\Jobs;

use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Models\CreditNote;
use App\Support\GotenbergClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * #409's PDF, the same shape RenderInvoiceJob has: the artifact under the
 * tenant's own prefix, and the guard on the artifact rather than on a status
 * — a Gutschrift the client may already hold is never re-rendered. The
 * document itself comes from RenderInvoicePdf, through the studio's chosen
 * layout, with negative amounts and the reference line (#419).
 */
class RenderCreditNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly CreditNote $creditNote) {}

    public function handle(GotenbergClient $gotenberg, RenderInvoicePdf $render): void
    {
        $creditNote = $this->creditNote->fresh(['template', 'installment.plan.job.project']);

        if ($creditNote === null) {
            return;
        }

        if ($creditNote->installment?->plan?->job?->project === null) {
            return;
        }

        // Same guard as RenderInvoiceJob's, and for the same reason (#416).
        $path = "credit-notes/{$creditNote->tenant_id}/{$creditNote->public_id}.pdf";

        if (Storage::disk('local')->exists($path)) {
            return;
        }

        $pdf = $gotenberg->htmlToPdf($render->forCreditNote($creditNote, snapshotPath: $path));

        Storage::disk('local')->put($path, $pdf);

        $creditNote->update(['pdf_path' => $path]);
    }
}
