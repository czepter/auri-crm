<?php

namespace App\Documents\Support;

use App\Documents\Enums\DocumentTemplateType;
use App\Settings\Support\StudioSettingsResolver;

/**
 * The shared PDF shell — T-12's "letterhead and footer are shared, not
 * copied into each template". Quote, Invoice and CreditNote are the document
 * types Gotenberg turns into a PDF, so each gets the studio's one configured
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

        return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            .$this->fontFace().self::STYLE
            .'</head><body>'.$html.'</body></html>';
    }

    /**
     * Die Hausschrift, in die Datei eingebettet statt verlinkt.
     *
     * Gotenberg rendert in einem eigenen Container und käme an /fonts nicht
     * heran; eine URL wäre je nach Umgebung mal erreichbar und mal nicht,
     * und ein Vertrag, der beim einen Lauf in Hanken Grotesk und beim
     * nächsten in DejaVu gesetzt ist, ist kein Beleg mehr, den man
     * wiedererkennt. Latin und Latin-Ext, weil die deutsche Typografie
     * (Gedankenstrich, ‚Anführung', ﬁ) im zweiten Schnitt liegt.
     *
     * Fehlt eine Datei — ein Build ohne public/fonts —, bleibt es beim
     * Fallback-Stack in STYLE statt bei einer @font-face-Regel ins Leere.
     */
    private function fontFace(): string
    {
        $faces = '';

        foreach (['hanken-grotesk-latin', 'hanken-grotesk-latin-ext'] as $file) {
            $path = public_path("fonts/{$file}.woff2");

            if (! is_readable($path)) {
                continue;
            }

            $data = base64_encode((string) file_get_contents($path));

            $faces .= "@font-face{font-family:'Hanken Grotesk';font-style:normal;font-weight:400 700;"
                ."src:url(data:font/woff2;base64,{$data}) format('woff2');}";
        }

        return $faces === '' ? '' : "<style>{$faces}</style>";
    }

    /**
     * Was die Bausteine an Satz brauchen, und keine Zeile mehr: die Zählung
     * der Klauseln, die Linien einer Unterschrift, der Seitenumbruch, die
     * rechtsbündigen Zahlenspalten.
     *
     * Steht hier statt im Editor, weil dieselbe Datei zu Gotenberg geht und
     * in die Vorschau — ein zweites Stylesheet für den Bildschirm hieße,
     * dass die Vorschau etwas anderes zeigt als der Kunde bekommt.
     */
    private const STYLE = <<<'CSS'
        <style>
          /* Papiermaße, keine Bildschirmmaße: 10.5pt Grundschrift ist das,
             was ein Angebot am Tisch verlangt, und liegt über dem 12px, das
             ein Browser von sich aus setzt. Silbentrennung an, weil deutsche
             Komposita in einer 16cm breiten Spalte sonst Löcher reißen. */
          body {
            margin: 0;
            font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.55;
            color: #191919;
            hyphens: auto;
            -webkit-hyphens: auto;
          }
          p { margin: 0 0 8pt; }
          h1, h2, h3 { margin: 16pt 0 6pt; line-height: 1.25; font-weight: 600; letter-spacing: -.01em; }
          h1 { font-size: 17pt; margin-top: 0; }
          h2 { font-size: 12.5pt; }
          h3 { font-size: 10.5pt; }
          /* Eine Überschrift allein am Seitenfuß ist eine verlorene Zeile. */
          h1, h2, h3 { break-after: avoid-page; page-break-after: avoid; }
          ul, ol { margin: 0 0 8pt; padding-left: 16pt; }
          li { margin: 2pt 0; }
          a { color: #191919; }
          blockquote { margin: 10pt 0; padding-left: 10pt; border-left: 1.5pt solid #E9E9E7; }
          blockquote p { margin: 0; }
          blockquote cite { display: block; margin-top: 3pt; font-size: 9pt; font-style: normal; color: #6B6B6B; }
          figure { margin: 10pt 0; }
          figure img { max-width: 100%; }
          figcaption { margin-top: 3pt; font-size: 8.5pt; color: #6B6B6B; }
          /* Eine Tabelle darf nicht über die Seitenkante zerfallen. */
          table { break-inside: avoid; page-break-inside: avoid; }
          .doc-table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
          .doc-table th, .doc-table td { padding: 4pt 6pt; border-bottom: .75pt solid #E9E9E7; text-align: left; vertical-align: top; font-size: 9.5pt; }
          .doc-table th { font-weight: 600; }
          .doc-table .doc-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
          .doc-checklist { list-style: none; padding: 0; margin: 8pt 0; }
          .doc-checklist li { margin: 2pt 0; }
          .doc-fineprint { font-size: 8pt; line-height: 1.45; color: #6B6B6B; }
          .doc-pagebreak { break-after: page; page-break-after: always; }

          /* Angebot-Drucklayouts — je ein Block pro App\Quotes\Enums\
             QuoteLayout-Fall, Referenzmarkup in QuoteLayoutSample.vue. Das
             Seitenpolster (padding auf dem jeweiligen Wurzel-div) ist hier
             genau das der Vorlage — ohne das sitzt der Inhalt an der Kante
             der Vorschau statt auf einem Blatt Papier. */
          .doc-layout-classic { padding: 48pt 54pt 30pt; }
          .doc-layout-classic .dlc-head { display: flex; justify-content: space-between; align-items: flex-start; }
          .doc-layout-classic .dlc-from { font-size: 8.5pt; color: #6B6B6B; margin-bottom: 6pt; }
          .doc-layout-classic .dlc-recipient { font-weight: 600; margin-top: 4pt; }
          .doc-layout-classic .dlc-meta { border-collapse: collapse; }
          .doc-layout-classic .dlc-meta td { padding: 2pt 0; font-size: 9pt; }
          .doc-layout-classic .dlc-meta td:first-child { color: #6B6B6B; padding-right: 16pt; }
          .doc-layout-classic .dlc-meta td:last-child { text-align: right; }
          .doc-layout-classic .dlc-title { font-size: 12pt; font-weight: 700; padding-bottom: 8pt; }
          .doc-layout-classic .doc-table { margin-top: 30pt; }
          .doc-layout-classic .dlc-summary { width: 180pt; margin-left: auto; margin-top: 6pt; }
          .doc-layout-classic .dlc-summary td:last-child { text-align: right; }
          .doc-layout-classic .dlc-total td { border-top: 1pt solid #191919; padding-top: 6pt; font-weight: 700; }

          .doc-layout-minimal { padding: 60pt 60pt 34pt; max-width: 380pt; margin: 0 auto; }
          .doc-layout-minimal .dlm-head { display: flex; justify-content: space-between; font-size: 9pt; color: #6B6B6B; margin: 32pt 0 14pt; }
          .doc-layout-minimal .dlm-to { font-size: 9.5pt; color: #6B6B6B; margin-bottom: 16pt; }
          .doc-layout-minimal .dlm-table { width: 100%; border-collapse: collapse; margin-top: 20pt; }
          .doc-layout-minimal .dlm-table td { padding: 8pt 0; vertical-align: top; }
          .doc-layout-minimal .dlm-table tr + tr td { border-top: .75pt solid #F0F0EE; }
          .doc-layout-minimal .dlm-table td:last-child { text-align: right; white-space: nowrap; padding-left: 12pt; }
          .doc-layout-minimal .dlm-desc { font-size: 8.5pt; color: #6B6B6B; }
          .doc-layout-minimal .dlm-total td { border-top: 1pt solid #191919 !important; font-weight: 600; padding-top: 9pt !important; }
          .doc-layout-minimal .dlm-grand { font-weight: 700; }

          .doc-layout-letter { padding: 42pt 54pt 30pt; background: #FFFEFC; }
          .doc-layout-letter .dll-top { display: flex; justify-content: space-between; font-size: 8pt; color: #6B6B6B; }
          .doc-layout-letter .dll-title { font-family: 'Hanken Grotesk', serif; font-size: 22pt; font-weight: 500; margin: 16pt 0 3pt; }
          .doc-layout-letter .dll-sub { font-size: 8pt; color: #6B6B6B; margin-bottom: 22pt; }
          .doc-layout-letter .dll-to { font-size: 9pt; color: #6B6B6B; margin-bottom: 16pt; }
          .doc-layout-letter .doc-table { margin-top: 16pt; }
          .doc-layout-letter .dll-total td { border-top: 1pt solid #191919; font-weight: 700; }

          .doc-layout-header_band .dhb-band { background: #1C6870; color: #fff; padding: 20pt 54pt; display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 18pt; }
          .doc-layout-header_band .dhb-band-title { font-size: 14pt; font-weight: 700; text-align: right; }
          .doc-layout-header_band .dhb-body { padding: 0 54pt 30pt; }
          .doc-layout-header_band .dhb-meta-row { display: flex; gap: 14pt; font-size: 8pt; color: #6B6B6B; padding-bottom: 8pt; border-bottom: 0.75pt solid #E9E9E7; margin-bottom: 14pt; }
          .doc-layout-header_band .dhb-to { margin-bottom: 16pt; }
          .doc-layout-header_band .doc-table { margin-top: 16pt; }
          .doc-layout-header_band .dhb-summary { width: 200pt; margin-left: auto; margin-top: 10pt; border: 0.75pt solid #E9E9E7; padding: 8pt 10pt; }
          .doc-layout-header_band .dhb-summary td:last-child { text-align: right; }
          .doc-layout-header_band .dhb-total td { border-top: 1pt solid #1C6870; font-weight: 700; color: #1C6870; }

          .doc-layout-structured { padding: 42pt 48pt 24pt; }
          .doc-layout-structured .dst-head { text-align: right; font-size: 12pt; font-weight: 700; margin-bottom: 14pt; }
          .doc-layout-structured .dst-cards { display: flex; gap: 10pt; margin-bottom: 12pt; }
          .doc-layout-structured .dst-card { flex: 1; border: 0.75pt solid #DCDAD6; padding: 8pt 10pt; margin-bottom: 10pt; }
          .doc-layout-structured .dst-label { font-size: 7pt; font-weight: 600; text-transform: uppercase; color: #6B6B6B; margin-bottom: 4pt; }
          .doc-layout-structured .dst-meta-grid { display: grid; grid-template-columns: auto 1fr; gap: 2pt 10pt; font-size: 9pt; }
          .doc-layout-structured .dst-items { padding: 0; }
          .doc-layout-structured .dst-items .doc-table { margin: 0; }
          .doc-layout-structured .dst-summary { margin-top: 12pt; }
          .doc-layout-structured .dst-summary td:last-child { text-align: right; }
          .doc-layout-structured .dst-total td { border-top: 1pt solid #191919; font-weight: 700; }
        </style>
        CSS;
}
