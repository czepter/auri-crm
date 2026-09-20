<?php

namespace App\Payments\Support\Layouts;

use App\Payments\Support\InvoiceDocument;

/**
 * #419. One implementation per App\Quotes\Enums\QuoteLayout case — the same
 * five, read from the same `document_layout` setting, because a studio picks a
 * look for its documents rather than one per document type.
 *
 * Every implementation emits the CSS classes its quote twin already emits
 * (`.doc-layout-classic` + `.dlc-*`, and so on), so the invoice inherits the
 * quote's table and summary styling from DocumentRenderer::STYLE rather than
 * from a second stylesheet that would drift from it.
 *
 * The invoice's § 14 UStG particulars are the layout's job now, not the
 * template's — a mandatory field must not be a block somebody can delete.
 * $templateBody is what is left: prose, already merge-field-resolved by
 * RenderInvoicePdf.
 */
interface InvoiceLayoutRenderer
{
    public function render(InvoiceDocument $document, array $mergeData, string $templateBody): string;
}
