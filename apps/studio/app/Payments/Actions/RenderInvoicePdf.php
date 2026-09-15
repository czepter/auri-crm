<?php

namespace App\Payments\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceDocument;
use App\Payments\Support\Layouts\ClassicInvoiceLayout;
use App\Payments\Support\Layouts\HeaderBandInvoiceLayout;
use App\Payments\Support\Layouts\InvoiceLayoutRenderer;
use App\Payments\Support\Layouts\LetterInvoiceLayout;
use App\Payments\Support\Layouts\MinimalInvoiceLayout;
use App\Payments\Support\Layouts\StructuredInvoiceLayout;
use App\Payments\Support\MergeDataSnapshot;
use App\Quotes\Enums\QuoteLayout;
use App\Settings\Support\StudioSettingsResolver;

/**
 * #419. What RenderQuotePdf does for an Angebot, for the Rechnung and the
 * Gutschrift: the layout comes from the studio's one `quote_layout` setting,
 * and the template supplies prose only.
 *
 * Before this, the invoice was entirely template content — address, meta
 * table, summary table and bank block were blocks in StarterTemplateCatalog.
 * Two things followed from that and both were wrong: a studio on the "Letter"
 * layout got a Letter quote and an invoice that looked like a form, and every
 * § 14 UStG mandatory field was a block somebody could delete.
 */
class RenderInvoicePdf
{
    public function __construct(
        private readonly StudioSettingsResolver $settings,
        private readonly DocumentMergeDataBuilder $mergeData,
        private readonly DocumentRenderer $renderer,
    ) {}

    /**
     * $snapshotPath, where given, is the document the merge data belongs to:
     * the array is frozen beside it on the first render and read back on
     * every later one (#416). A preview passes none and always resolves live.
     */
    public function forInstallment(PaymentInstallment $installment, ?QuoteLayout $layoutOverride = null, ?string $snapshotPath = null): string
    {
        $project = $installment->plan?->job?->project;
        $build = fn (): array => $this->mergeData->fromProject($project, $this->settings, $installment);

        $data = $snapshotPath === null ? $build() : MergeDataSnapshot::remember($snapshotPath, $build);

        return $this->wrap(
            DocumentTemplateType::Invoice,
            InvoiceDocument::forInvoice($installment, $data, $this->settings),
            $data,
            $installment->invoiceTemplate,
            $layoutOverride,
        );
    }

    public function forCreditNote(CreditNote $creditNote, ?QuoteLayout $layoutOverride = null, ?string $snapshotPath = null): string
    {
        $project = $creditNote->installment?->plan?->job?->project;
        $build = fn (): array => $this->mergeData->fromProject($project, $this->settings, $creditNote->installment, $creditNote);

        $data = $snapshotPath === null ? $build() : MergeDataSnapshot::remember($snapshotPath, $build);

        return $this->wrap(
            DocumentTemplateType::CreditNote,
            InvoiceDocument::forCreditNote($creditNote, $data, $this->settings),
            $data,
            $creditNote->template,
            $layoutOverride,
        );
    }

    /**
     * The pinned template where the caller has one — an issued document's
     * wording must not change under it — and the current one otherwise, which
     * is what a settings preview needs.
     */
    private function wrap(
        DocumentTemplateType $type,
        InvoiceDocument $document,
        array $data,
        ?DocumentTemplate $template,
        ?QuoteLayout $layoutOverride,
    ): string {
        $template ??= DocumentTemplate::query()->current()->ofType($type)->orderBy('id')->first();

        $body = $this->layoutRenderer($layoutOverride)->render(
            $document,
            $data,
            MergeFieldResolver::resolve($template?->body ?? '', $data),
        );

        return $this->renderer->wrap($type, $body, $this->settings);
    }

    /** The same five as the quote, read from the same setting — see QuoteLayout. */
    private function layoutRenderer(?QuoteLayout $override = null): InvoiceLayoutRenderer
    {
        $layout = $override ?? QuoteLayout::tryFrom($this->settings->quoteLayout()) ?? QuoteLayout::Classic;

        return match ($layout) {
            QuoteLayout::Classic => new ClassicInvoiceLayout,
            QuoteLayout::Minimal => new MinimalInvoiceLayout,
            QuoteLayout::Letter => new LetterInvoiceLayout,
            QuoteLayout::HeaderBand => new HeaderBandInvoiceLayout,
            QuoteLayout::Structured => new StructuredInvoiceLayout,
        };
    }
}
