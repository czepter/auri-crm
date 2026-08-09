<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;
use App\Settings\Support\StudioSettingsResolver;

/**
 * The shared PDF shell — T-12's "letterhead and footer are shared, not
 * copied into each template". Contract and Quote are the two document types
 * Gotenberg ever turns into a PDF, so both get the studio's one configured
 * letterhead above their resolved body and one configured footer below it
 * (App\Settings\Support\StudioSettingsResolver, the Dokumente pane). Mail is
 * not a PDF — App\Quotes\Notifications\QuoteSent builds an email, not a
 * Gotenberg request — so its resolved body passes through untouched.
 */
class DocumentRenderer
{
    public function wrap(DocumentTemplateType $type, string $resolvedBody, StudioSettingsResolver $settings): string
    {
        if ($type === DocumentTemplateType::Mail) {
            return $resolvedBody;
        }

        $letterhead = $settings->documentLetterheadHtml() ?? '';
        $footer = $settings->documentFooterHtml() ?? '';

        return $this->wrapDocument($letterhead.$resolvedBody.$footer);
    }

    /** Gotenberg's chromium route wants a full document, not a fragment. */
    private function wrapDocument(string $html): string
    {
        if (str_contains($html, '<html')) {
            return $html;
        }

        return '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
    }
}
