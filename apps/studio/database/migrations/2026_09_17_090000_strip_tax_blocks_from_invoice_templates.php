<?php

use App\Documents\Enums\DocumentTemplateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #480. Steuernummer and USt-IdNr. are the layout's now (InvoiceDocument's
 * meta rows), because an invoice's letter text is edited per invoice and a
 * § 14 particular must not be something that can be deleted there. A current
 * template that still carries them would print them twice.
 *
 * Current versions only, for the reason 2026_09_14_000500 gives.
 */
return new class extends Migration
{
    private const MARKERS = ['studio.taxNumber', 'studio.vatId'];

    public function up(): void
    {
        $rows = DB::table('document_templates')
            ->where('is_current', true)
            ->whereIn('type', [DocumentTemplateType::Invoice->value, DocumentTemplateType::CreditNote->value])
            ->get(['id', 'body', 'blocks']);

        foreach ($rows as $row) {
            DB::table('document_templates')->where('id', $row->id)->update([
                'body' => $this->strippedBody($row->body ?? ''),
                'blocks' => $this->strippedBlocks($row->blocks),
            ]);
        }
    }

    public function down(): void {}

    /**
     * Top-level elements only — a block serialises to exactly one of them,
     * and dropping a nested node would leave half a table behind. The XML
     * prolog is what stops DOMDocument reading UTF-8 as Latin-1, the same
     * note App\Documents\Support\InlineHtmlSanitizer already carries.
     */
    private function strippedBody(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;

        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $body = $loaded === false ? null : $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return $html;
        }

        $out = '';

        foreach ($body->childNodes as $child) {
            $rendered = $document->saveHTML($child);

            if (! $this->carriesMarker($rendered)) {
                $out .= $rendered;
            }
        }

        return $out;
    }

    private function strippedBlocks(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['blocks']) || ! is_array($decoded['blocks'])) {
            return $json;
        }

        $decoded['blocks'] = array_values(array_filter(
            $decoded['blocks'],
            fn ($block) => ! $this->carriesMarker((string) json_encode($block)),
        ));

        return (string) json_encode($decoded);
    }

    private function carriesMarker(string $haystack): bool
    {
        foreach (self::MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }
};
