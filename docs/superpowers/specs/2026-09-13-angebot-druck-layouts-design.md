# Angebot-Drucklayouts — design

**Date:** 2026-09-13
**Status:** design

---

## 1. Warum das existiert

`RenderQuotePdf::items()`/`row()`/`summary()` (`apps/studio/app/Quotes/Actions/RenderQuotePdf.php`)
bauen die Positionstabelle und die Summenzeilen als reine PHP-String-Konkatenation
— ohne die `.doc-table`-Klasse, die `DocumentRenderer::STYLE` dafür längst
mitbringt, und ohne die Angebotsnummer oder das Gültig-bis-Datum überhaupt
auszugeben. Fünf Drucklayouts (Klassisch, Minimal, Brief, Kopfband, Struktur)
sind im Design-Canvas dieser Session entworfen, mit realistischen
Beispieldaten und echten Marken-Tokens (`resources/css/tokens.css`). Diese
Spec macht daraus eine echte, im Studio wählbare Funktion statt einer
Bildergalerie: ein Layout pro Studio, eine echte HTML-Vorschau mit
Mock-Daten in Einstellungen → Belege & Fristen, an derselben Stelle, an der
die übrige Angebots-Konfiguration (Gültigkeit, Zahlungsziel, Anzahlung,
Widerrufsbelehrung) schon liegt.

## 2. Scope

**Drin:** ein `quote_layout`-Feld je Studio (fünf Werte: `classic`, `minimal`,
`letter`, `header_band`, `structured`); eine `QuoteLayoutRenderer`-Abstraktion,
die für jedes Layout Empfänger, Meta-Box/Kopf, Positionstabelle und
Summenzeilen baut; ein `RadioGroup`-Umschalter in `DocumentsForm.vue` mit
echter HTML-Live-Vorschau (kein Skeleton) über einen neuen
Vorschau-Endpunkt mit fest verdrahteten Beispieldaten; die fehlende
Angebotsnummer/Gültig-bis-Ausgabe, die `Classic` als erstes Layout korrigiert.

**Draußen, benannt damit die Auslassung eine Entscheidung ist:** die
Regler-Darstellung (`Quote::hasSlider()`/`sliderRows()`) — bleibt eine
einzige, layoutunabhängige Darstellung; ein Regler hat keine Positionstabelle,
die fünf Layouts unterscheiden könnten, und die Zeilenlogik dort ist schon
komplex genug, ohne sie zu verfünffachen. Eine Layout-Wahl pro
`DocumentTemplate`/`shoot_kind` statt studioweit — abgelehnt: einfacher
Einstieg, eine Auswahl pro Studio statt einer pro Vorlage. Ein sechstes,
eigenes Layout oder ein Custom-CSS-Editor — nicht angefragt.

## 3. Datenmodell

Neue Spalte auf der Studio-Settings-Tabelle: `quote_layout` (string, default
`'classic'`), analog zu `vat_mode`. Neues Enum `App\Quotes\Enums\QuoteLayout`:

```php
enum QuoteLayout: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Letter = 'letter';
    case HeaderBand = 'header_band';
    case Structured = 'structured';
}
```

`StudioSettingsResolver::quoteLayout(): QuoteLayout` liest die Spalte,
gecastet über das Enum, mit `Classic` als Fallback bei `null`/unbekanntem
Wert — dieselbe Nachsicht, die `vatMode()` heute schon für einen leeren Wert
zeigt.

## 4. `QuoteLayoutRenderer`

```php
interface QuoteLayoutRenderer
{
    public function render(Quote $quote, array $mergeData, string $templateBody): string;
}
```

Fünf Implementierungen unter `app/Quotes/Support/Layouts/`
(`ClassicQuoteLayout`, `MinimalQuoteLayout`, `LetterQuoteLayout`,
`HeaderBandQuoteLayout`, `StructuredQuoteLayout`), je eine Übersetzung des
entsprechenden Canvas-Entwurfs in PHP: Empfänger-Platzierung, Meta-Box/Kopf
(Angebotsnummer, Datum, Gültig bis — bei `Classic` neu, bei den anderen vier
von Anfang an vorhanden), die Positionstabelle (inkl. Menge-Spalte, wo im
Entwurf vorgesehen) und die Summenkette (Zwischensumme → Rabatt → Netto →
USt. → Gesamtbetrag, wie `summary()` sie heute schon berechnet — nur die
Markup-Form ändert sich pro Layout, nicht die Zahlen).

`RenderQuotePdf::html()` ersetzt seinen heutigen Ablauf
(`recipient() . body . letter(intro) . items() . letter(outro)`) durch:

```php
$layout = $layoutOverride ?? $this->settings->quoteLayout();
$renderer = $this->layoutRenderers[$layout->value]; // via Container-Tag oder match()
return $this->renderer->wrap(
    DocumentTemplateType::Quote,
    $renderer->render($quote, $data, $template?->body ?? ''),
    $this->settings,
);
```

Der `sliderRows()`-Zweig bleibt vollständig außerhalb dieser Abstraktion —
`items()`s bestehendes `if ($quote->hasSlider())` greift weiterhin zuerst,
unabhängig vom gewählten Layout.

## 5. CSS

`DocumentRenderer::STYLE` bekommt die layoutspezifischen Regeln
(Kopfband-Farbfläche, Struktur-Kartenrahmen, Brief-Serifenüberschrift) als
zusätzliche, unter `.doc-layout-{variant}` (z. B. `.doc-layout-header_band`)
skopierte Blöcke im selben Konstante — nicht als fünf getrennte Stylesheets.
Jeder `QuoteLayoutRenderer` wrappt seine Ausgabe in `<div
class="doc-layout-{variant}">…</div>`; Gotenberg und die
Einstellungen-Vorschau lesen dieselbe Datei, bleiben also pixelgleich, wie es
der bestehende Kommentar in `DocumentRenderer.php` für Vertrag/Angebot schon
verspricht.

## 6. Vorschau

Neuer Endpunkt `GET /einstellungen/dokumente/angebot-vorschau?layout=classic`
(staff-authentifiziert, studiogebunden, kein Projekt-/Quote-Bezug nötig).
Baut über `App\Quotes\Support\SampleQuoteData` ein rein
speicherinternes, nie persistiertes `Quote`+`QuoteItem[]` — ein
Empfänger, zwei Positionen (eine mit Rabatt und USt., eine inklusive), analog
zum Canvas-Mock — und ruft `RenderQuotePdf::html($sample, layoutOverride:
QuoteLayout::from($request->layout))` auf. Antwort: rohes HTML, `Content-Type:
text/html`, wie der bestehende `DocumentTemplateController::preview()`-Weg.

`DocumentsForm.vue` bekommt eine neue Sektion "Layout" (Kicker + `RadioGroup`
mit den fünf Werten) oberhalb der bestehenden Angebot-Sektion.
`DocumentPreview.vue`s heutige Skeleton-Karte (graue Balken) weicht einem
`<iframe sandbox="" :srcdoc="sheet">`, das bei jedem Radio-Wechsel den neuen
Endpunkt mit dem gewählten `layout`-Query-Param abfragt — ohne Debounce, da
ein Klick kein Tastaturstrom ist. Solange kein Layout aktiv angewählt wurde,
zeigt die Vorschau das gespeicherte Setting.

## 7. Fehlerfälle & Randfälle

Fehlt die Spalte (Migration nicht gelaufen) oder trägt sie einen unbekannten
Wert: Fallback auf `Classic` — das Layout, das dem heutigen (nach dem
`.doc-table`-Fix bereits geplanten) Erscheinungsbild am nächsten kommt, also
ändert ein Studio, das die neue Einstellung nie öffnet, sein gedrucktes
Angebot nicht sichtbar. Der Vorschau-Endpunkt berührt keine echten
Projekt-/Kontaktdaten, braucht also keine Quote-scoped Autorisierung — nur
das normale Staff-Gate der Einstellungen-Routen.

## 8. Tests

Ein Pest-Test pro Layout, der `RenderQuotePdf::html()` gegen ein
layout-typisches Merkmal prüft (`toContain`-Stil wie
`QuoteRecipientTest.php`) — für `Classic` insbesondere, dass die
Angebotsnummer jetzt überhaupt erscheint, als Regressionstest gegen die
heutige Lücke. Ein Test, dass der `layout`-Query-Param der Vorschau das
gespeicherte Setting überschreibt, ohne etwas zu persistieren. Ein Test, dass
ein leeres/unbekanntes `quote_layout` auf `Classic` zurückfällt.
