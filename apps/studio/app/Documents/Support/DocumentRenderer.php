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
             was ein Vertrag am Tisch verlangt, und liegt über dem 12px, das
             ein Browser von sich aus setzt. Silbentrennung an, weil deutsche
             Komposita in einer 16cm breiten Spalte sonst Löcher reißen. */
          body {
            counter-reset: doc-clause;
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
          /* Weder eine Tabelle noch ein Unterschriftsfeld darf über die
             Seitenkante zerfallen. Eine Klausel schon: sie kann länger sein
             als eine Seite, und was nicht umbrechen darf, läuft über. */
          table, .doc-signature { break-inside: avoid; page-break-inside: avoid; }
          .doc-table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
          .doc-table th, .doc-table td { padding: 4pt 6pt; border-bottom: .75pt solid #E9E9E7; text-align: left; vertical-align: top; font-size: 9.5pt; }
          .doc-table th { font-weight: 600; }
          .doc-table .doc-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
          .doc-clause { counter-increment: doc-clause; margin: 12pt 0; }
          .doc-clause h3 { margin: 0 0 3pt; }
          .doc-clause h3::before { content: "§ " counter(doc-clause) " "; }
          .doc-clause p { margin: 0; }
          .doc-checklist { list-style: none; padding: 0; margin: 8pt 0; }
          .doc-checklist li { margin: 2pt 0; }
          .doc-fineprint { font-size: 8pt; line-height: 1.45; color: #6B6B6B; }
          .doc-signature-place { margin: 20pt 0 0; }
          .doc-signature { display: flex; gap: 24pt; margin-top: 28pt; }
          .doc-signature-line { flex: 1; }
          .doc-signature-rule { display: block; border-top: .75pt solid #191919; margin-bottom: 3pt; }
          .doc-signature-role { display: block; font-size: 8.5pt; color: #6B6B6B; }
          .doc-signature-name { display: block; font-size: 9.5pt; }
          .doc-pagebreak { break-after: page; page-break-after: always; }
        </style>
        CSS;
}
