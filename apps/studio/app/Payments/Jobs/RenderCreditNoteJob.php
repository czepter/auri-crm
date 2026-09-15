<?php

namespace App\Payments\Jobs;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Payments\Models\CreditNote;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * #409's PDF, the same shape RenderInvoiceJob has: merge fields resolved
 * here, the shared letterhead and footer from DocumentRenderer, the artifact
 * under the tenant's own prefix, and the guard on the artifact rather than on
 * a status — a Gutschrift the client may already hold is never re-rendered.
 */
class RenderCreditNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly CreditNote $creditNote) {}

    public function handle(
        GotenbergClient $gotenberg,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
        DocumentRenderer $renderer,
    ): void {
        $creditNote = $this->creditNote->fresh(['template', 'installment.plan.job.project']);

        if ($creditNote === null || $creditNote->pdf_path !== null) {
            return;
        }

        $template = $creditNote->template;
        $project = $creditNote->installment?->plan?->job?->project;

        if ($template === null || $project === null) {
            return;
        }

        $resolved = MergeFieldResolver::resolve(
            $template->body,
            $mergeData->fromProject($project, $settings, $creditNote->installment, $creditNote),
        );

        $pdf = $gotenberg->htmlToPdf($renderer->wrap(DocumentTemplateType::CreditNote, $resolved, $settings));

        $path = "credit-notes/{$creditNote->tenant_id}/{$creditNote->public_id}.pdf";
        Storage::disk('local')->put($path, $pdf);

        $creditNote->update(['pdf_path' => $path]);
    }
}
