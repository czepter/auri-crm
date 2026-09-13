<?php

namespace App\Quotes\Support\Layouts;

use App\Quotes\Models\Quote;

/**
 * One implementation per App\Quotes\Enums\QuoteLayout case. Builds the full
 * body RenderQuotePdf wraps in the shared letterhead/footer — recipient,
 * meta (Angebotsnummer, Datum, Gültig bis), the classic item table and the
 * summary chain (Zwischensumme → Rabatt → Netto → USt. → Gesamtbetrag).
 * Never called for a slider-priced quote — RenderQuotePdf::html() branches
 * on Quote::hasSlider() before any layout dispatch.
 *
 * Reference markup for every implementation:
 * apps/studio/resources/js/admin/pages/Settings/previews/
 * QuoteLayoutSample.vue (Storybook: "Dokumente/Angebot-Layouts").
 */
interface QuoteLayoutRenderer
{
    /**
     * $templateBody is the already merge-field-resolved DocumentTemplate
     * body (intro/legal prose) — RenderQuotePdf resolves it once via
     * MergeFieldResolver before calling in, so no implementation touches
     * MergeFieldResolver itself.
     *
     * @param  array<string, mixed>  $mergeData
     */
    public function render(Quote $quote, array $mergeData, string $templateBody): string;
}
