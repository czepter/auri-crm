<?php

namespace App\Payments\Actions;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Models\StudioSettings;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Support\InvoiceDocument;
use App\Payments\Support\InvoiceMergeData;
use App\Payments\Support\Layouts\ClassicInvoiceLayout;
use App\Payments\Support\Layouts\HeaderBandInvoiceLayout;
use App\Payments\Support\Layouts\InvoiceLayoutRenderer;
use App\Payments\Support\Layouts\LetterInvoiceLayout;
use App\Payments\Support\Layouts\MinimalInvoiceLayout;
use App\Payments\Support\Layouts\StructuredInvoiceLayout;
use App\Payments\Support\MergeDataSnapshot;
use App\Quotes\Enums\QuoteLayout;

/**
 * #419. What RenderQuotePdf does for an Angebot, for the Rechnung and the
 * Gutschrift: the layout comes from the studio's one `document_layout` setting,
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
        private readonly InvoiceMergeData $mergeData,
        private readonly DocumentRenderer $renderer,
    ) {}

    /**
     * $snapshotPath, where given, is the document the merge data belongs to:
     * the array is frozen beside it on the first render and read back on
     * every later one (#416). A preview passes none and always resolves live.
     */
    public function forInvoice(Invoice $invoice, ?QuoteLayout $layoutOverride = null, ?string $snapshotPath = null): string
    {
        $build = fn (): array => $this->mergeData->forInvoice($invoice);
        $data = $snapshotPath === null ? $build() : MergeDataSnapshot::remember($snapshotPath, $build);

        return $this->wrap(
            DocumentTemplateType::Invoice,
            InvoiceDocument::forInvoice($invoice, $data),
            $data,
            // #480: the invoice's letter is its own intro and outro, filled
            // from the template when it was written — not the template again.
            '',
            $layoutOverride,
        );
    }

    public function forCreditNote(CreditNote $creditNote, ?QuoteLayout $layoutOverride = null, ?string $snapshotPath = null): string
    {
        $build = fn (): array => $this->mergeData->forInvoice($creditNote->invoice, $creditNote);
        $data = $snapshotPath === null ? $build() : MergeDataSnapshot::remember($snapshotPath, $build);

        return $this->wrap(
            DocumentTemplateType::CreditNote,
            InvoiceDocument::forCreditNote($creditNote, $data),
            $data,
            MergeFieldResolver::resolve(($creditNote->template ?? $this->currentTemplate(DocumentTemplateType::CreditNote))?->body ?? '', $data),
            $layoutOverride,
        );
    }

    /** The current template, which is what an invoice's letter text starts from. */
    public function currentTemplate(DocumentTemplateType $type): ?DocumentTemplate
    {
        return DocumentTemplate::query()->current()->ofType($type)->orderBy('id')->first();
    }

    private function wrap(DocumentTemplateType $type, InvoiceDocument $document, array $data, string $prose, ?QuoteLayout $layoutOverride): string
    {
        $body = $this->layoutRenderer($layoutOverride)->render($document, $data, $prose);

        return $this->renderer->wrap($type, $body);
    }

    /** The same five as the quote, read from the same setting — see QuoteLayout. */
    private function layoutRenderer(?QuoteLayout $override = null): InvoiceLayoutRenderer
    {
        $layout = $override ?? QuoteLayout::tryFrom(StudioSettings::current()->document_layout) ?? QuoteLayout::Classic;

        return match ($layout) {
            QuoteLayout::Classic => new ClassicInvoiceLayout,
            QuoteLayout::Minimal => new MinimalInvoiceLayout,
            QuoteLayout::Letter => new LetterInvoiceLayout,
            QuoteLayout::HeaderBand => new HeaderBandInvoiceLayout,
            QuoteLayout::Structured => new StructuredInvoiceLayout,
        };
    }
}
