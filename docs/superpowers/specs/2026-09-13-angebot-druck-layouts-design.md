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

## 3. Die fünf Layouts

Referenzmarkup liegt versioniert im Repo, nicht nur im Design-Canvas:
[`QuoteLayoutSample.vue`](../../../apps/studio/resources/js/admin/pages/Settings/previews/QuoteLayoutSample.vue)
mit den fünf gleichnamigen Storybook-Stories unter „Dokumente/Angebot-Layouts"
(`QuoteLayoutSample.stories.ts`, `npm run storybook` in `apps/studio` → Klassisch/
Minimal/Brief/Kopfband/Struktur) — dieselben Beispieldaten wie im
Entwurf (fiktive Muster GmbH, 2× Fotoreportage à 250 €, eine
inklusive Position, 10 % Rabatt, 19 % USt.), exakte HTML-Struktur und CSS,
A4-Maß (794×1123px). Jede Story ist die Referenz für die gleichnamige
`QuoteLayoutRenderer`-Implementierung aus §5 — Struktur und Reihenfolge der
Elemente dort, nicht nur die Farben, sind das, was die PHP-Klasse nachbaut.
Ursprungsentwurf mit denselben fünf Ständen, falls der Canvas noch
gebraucht wird:
[Angebot Vorlagen](https://claude.ai/code/artifact/c6c862ab-3e50-4fac-beca-9badc777ee25).

- **`classic`** (`ClassicQuoteLayout`, Artboard „Klassisch"): Absender/Empfänger
  links, Meta-Box rechts daneben (Angebotsnr., Datum, Gültig bis als
  Label/Wert-Zeilen). Positionstabelle mit Kopfzeile (Beschreibung, Menge,
  Einzelpreis, Betrag), `.doc-table`-Rahmen. Summenblock rechtsbündig unter
  der Tabelle. Kommt der heutigen — nach dem `.doc-table`-Fix geplanten —
  Optik am nächsten und ist der Fallback für ein leeres `quote_layout`.
- **`minimal`** (`MinimalQuoteLayout`, Artboard „Minimal"): keine Meta-Box,
  keine Tabellenlinien. Eine Zeile Angebotsnummer + Gültig-bis-Datum über dem
  Empfänger, danach eine schmale zweispaltige Liste (Titel+Beschreibung
  links, Betrag rechts) ohne Rahmen — bewusst dieselbe Anordnung wie
  `resources/js/portal/pages/Quotes/Show.vue`, damit Kundenportal und Druck
  gleich aussehen. Menge steht inline im Titel (`2 × 250,00 €`) statt in
  einer eigenen Spalte.
- **`letter`** (`LetterQuoteLayout`, Artboard „Brief"): Angebot als
  Anschreiben — Ort/Datum oben rechts, große Serifen-Überschrift „Angebot",
  Anrede-Absatz vor der Positionstabelle, Schlussabsatz mit Unterschriftszeile
  danach. Positionstabelle schlicht, mit Kopfzeile, aber ohne Kartenrahmen.
- **`header_band`** (`HeaderBandQuoteLayout`, Artboard „Kopfband"): volle
  Breite Farbfläche oben (Akzentfarbe `--accent`/`#1C6870`) mit Wortmarke und
  „Angebot Nr. …“; darunter eine Meta-Zeile (Datum · Gültig bis · Kundennr.)
  vor Empfänger und Tabelle. Tabellenkopf leicht akzentfarben hinterlegt,
  Summenblock als eigene umrandete Karte, Fußzeile mit dicker Akzentlinie.
- **`structured`** (`StructuredQuoteLayout`, Artboard „Struktur"):
  durchgehend Karten mit Rahmen — Empfänger und Meta-Daten je eine eigene
  Karte nebeneinander (2-spaltiges Grid), Positionstabelle mit alternierend
  eingefärbten Zeilen in einer eigenen Karte, Summenblock und Fußzeile
  ebenfalls als Karten. Dichteste der fünf Varianten, am ehesten für
  B2B-Empfänger gedacht.

Alle fünf teilen dieselbe Summenkette (Zwischensumme → Rabatt → Netto → USt.
→ Gesamtbetrag) und denselben Fußzeilen-Inhalt (Studioname, Adresse, E-Mail,
Website, Steuernummer) — nur Anordnung und Rahmung unterscheiden sich, wie in
§6 beschrieben.

## 4. Datenmodell

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

## 5. `QuoteLayoutRenderer`

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

## 6. CSS

`DocumentRenderer::STYLE` bekommt die layoutspezifischen Regeln
(Kopfband-Farbfläche, Struktur-Kartenrahmen, Brief-Serifenüberschrift) als
zusätzliche, unter `.doc-layout-{variant}` (z. B. `.doc-layout-header_band`)
skopierte Blöcke im selben Konstante — nicht als fünf getrennte Stylesheets.
Jeder `QuoteLayoutRenderer` wrappt seine Ausgabe in `<div
class="doc-layout-{variant}">…</div>`; Gotenberg und die
Einstellungen-Vorschau lesen dieselbe Datei, bleiben also pixelgleich, wie es
der bestehende Kommentar in `DocumentRenderer.php` für Vertrag/Angebot schon
verspricht.

## 7. Vorschau

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

## 8. Fehlerfälle & Randfälle

Fehlt die Spalte (Migration nicht gelaufen) oder trägt sie einen unbekannten
Wert: Fallback auf `Classic` — das Layout, das dem heutigen (nach dem
`.doc-table`-Fix bereits geplanten) Erscheinungsbild am nächsten kommt, also
ändert ein Studio, das die neue Einstellung nie öffnet, sein gedrucktes
Angebot nicht sichtbar. Der Vorschau-Endpunkt berührt keine echten
Projekt-/Kontaktdaten, braucht also keine Quote-scoped Autorisierung — nur
das normale Staff-Gate der Einstellungen-Routen.

## 9. Tests

Ein Pest-Test pro Layout, der `RenderQuotePdf::html()` gegen ein
layout-typisches Merkmal prüft (`toContain`-Stil wie
`QuoteRecipientTest.php`) — für `Classic` insbesondere, dass die
Angebotsnummer jetzt überhaupt erscheint, als Regressionstest gegen die
heutige Lücke. Ein Test, dass der `layout`-Query-Param der Vorschau das
gespeicherte Setting überschreibt, ohne etwas zu persistieren. Ein Test, dass
ein leeres/unbekanntes `quote_layout` auf `Classic` zurückfällt.
