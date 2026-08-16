<?php

namespace App\Payments\Jobs;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\GotenbergClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * The invoice PDF, rendered exactly the way RenderContractJob renders a
 * contract: merge fields resolved here rather than in the caller, the shared
 * letterhead and footer from DocumentRenderer rather than baked into the
 * template, and the artifact written under the tenant's own prefix.
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

    public function handle(
        GotenbergClient $gotenberg,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
        DocumentRenderer $renderer,
    ): void {
        $installment = $this->installment->fresh(['invoiceTemplate', 'plan.job.project']);

        if ($installment === null || ! $installment->isInvoiced() || $installment->invoice_path !== null) {
            return;
        }

        $template = $installment->invoiceTemplate;
        $project = $installment->plan?->job?->project;

        if ($template === null || $project === null) {
            return;
        }

        $resolved = MergeFieldResolver::resolve(
            $template->body,
            $mergeData->fromProject($project, $settings, $installment),
        );

        $pdf = $gotenberg->htmlToPdf($renderer->wrap(DocumentTemplateType::Invoice, $resolved, $settings));

        $path = "invoices/{$installment->tenant_id}/{$installment->public_id}.pdf";
        Storage::disk('local')->put($path, $pdf);

        $installment->update(['invoice_path' => $path]);
    }
}
