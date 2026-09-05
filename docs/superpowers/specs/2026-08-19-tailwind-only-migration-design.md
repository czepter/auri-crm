# Tailwind-only — Migration der eigenen Stylesheets

Status: 15 von 175 Templates tragen noch eine eigene Klasse — 32 Namen, 71
Vorkommen. `crm.css` ist bis auf seinen Tokenblock aufgeloest.
Datum: 2026-08-20

## Ziel

Ein Entwickler soll Styles lesen können, ohne ein hauseigenes Stylesheet zu
kennen. Heute stehen 484 Klassennamen in sieben Sheets, 298 davon mehrfach
definiert — `.lab` viermal, mit vier verschiedenen Werten. Welcher gilt,
entscheidet der Vorfahre.

Nicht Ziel: Bundle-Größe, Spezifitätskonflikte, Ablösung von `mockups/`.

Nicht im Umfang: `apps/gallery`. Die Astro-App hat kein Tailwind und eigene
Sheets (315 Zeilen); sie zu migrieren hieße, Tailwind dort erst einzuführen.

## Ausgangslage

| | |
|---|---|
| Eigene Sheets | 2741 Zeilen, 7 Dateien, 1124 Regeln |
| Regeln mit Element-Selektor | 325 (29 %) |
| Klassen definiert / genutzt | 484 / 371 |
| Klassenvorkommen in Templates | 3001, davon 2032 semantisch (68 %) |
| Vue-Dateien mit semantischen Klassen | 89 von 151 |
| Klassen in ≥3 Dateien / in genau 1 | 108 / 204 |
| Gescopte Regeln | `crm.css` 476, `gallery.css` 150 |
| Ungescopte Regeln (treffen alles) | ~492 |
| Breakpoints | 8 eigene, keiner ist Tailwind-Default |
| Visual-Regression-Netz | keins |

Zwei Messwerte tragen den Plan:

**Die Systeme berühren sich nicht.** Von 289 mehrteiligen `class`-Attributen
sind 9 gemischt, davon 7 `cn()`-Aufrufe oder Objekt-Bindings. Kein Element
trägt heute beide Sprachen — deshalb ist jede Datei einzeln umstellbar.

**Kein Sheet gehört wenigen Dateien.** `.card`, `.on`, `.row`, `.n` stehen in
mehreren Sheets. Nur die gescopten Teile sind sauber besessen. Fortschritt
misst sich in migrierten Screens, nicht in gelöschten Dateien.

## Entscheidungen

1. **Token bleiben, Klassen gehen.** Die `:root`-Variablen überleben und
   werden über `@theme inline` als Tailwind-Namen exponiert. Das
   Klassenvokabular verschwindet.
2. **Screen für Screen**, nicht Sheet für Sheet. Ein Sheet ist nicht
   abschließbar, ohne fast alle 89 Dateien anzufassen.
3. **Baselines zuerst.** Ohne Assertion fallen Abweichungen erst im Betrieb auf.
4. **Komponenten beim dritten Vorkommen**, nicht vorab.

## §1 Der Schnitt

`data-theme="crm"` ist heute zweierlei: Selektor-Präfix für 476 Regeln und
Scope für einen eigenen Token-Satz (`--ink:#1D1D1F` statt `#191919`, eigener
System-Font-Stack, Skala `--s1`…`--s7`). Nur die erste Rolle soll weg.

Der Token-Block bekommt einen zweiten Selektor:

    [data-theme="crm"], [data-tokens="crm"] { --paper: …; --ink: …; --s1: …; }

Alle 476 Regeln behalten `[data-theme="crm"]`. Eine Datei migrieren heißt:
Attribut auf `data-tokens="crm"` umschreiben, Klassen ersetzen. Die Regeln
greifen nicht mehr, die Token schon. Rückrollbar durch ein Attribut.

`gallery.css` hat keinen eigenen Token-Block — dort genügt das Entfernen von
`data-theme="gallery"`.

Drei ungescopte Element-Regeln schlagen auf migriertes Markup durch: `svg`
und `h1` in `tokens.css`, `h1` in `portal.css`. `body` ist gewollter Reset.

Sie beißen härter als gedacht. Die eigenen Sheets werden ungelayert
importiert, Tailwind legt seine Utilities in `@layer utilities` — und
ungelayertes CSS gewinnt gegen jede Layer-Regel, unabhängig von der
Spezifitaet. `svg { stroke-width: 1.75 }` schlaegt damit jedes
`stroke-[1.6]`, das an einem migrierten Icon steht.

Die drei nehmen migriertes Markup deshalb aus:

    svg:not(:where([data-tokens] svg)) { ... }

Migriertes Markup markiert sich mit `data-tokens`. Wo eine Oberflaeche eine
eigene Palette hat, traegt das Attribut ihren Namen (`data-tokens="crm"`); wo
nicht, steht es ohne Wert (`<div data-tokens>`) und dient nur als Marke. Ein
Screen braucht es nur, wenn er ein `h1` oder ein `svg` selbst setzen will —
`Staff/Index` etwa will die globale Ueberschrift behalten und bleibt ohne.

`:where()` ist dabei tragend: `:not()` erbt sonst die Spezifitaet seines
Arguments, und ein blankes `:not([data-tokens] h1)` ueberholt `.letter h1` —
womit jede unportierte Seite ihre Ueberschrift verliert.

Verworfen: die Sheets in `@layer components` legen. Das waere der saubere
Weg, kippt aber vierzehn Regeln, die shadcn-Komponenten absichtlich ueber
`[data-slot=...]` ueberschreiben — `.formfoot [data-slot="button"]
[data-variant="default"] { margin-left: 0 }` und Verwandte. Gemessen an den
Baselines: sechs Formularseiten verschoben ihre Buttons.

Erledigt, und inzwischen abgeschlossen: `data-theme="crm"` steht in keinem
Template mehr, die 476 Regeln sind weg, und `crm.css` deklariert seine Token
nur noch unter `[data-tokens="crm"]`. Der zweite Selektor war das Geruest
dafuer, nicht der Endstand.

## §2 Token-Schicht

**Abstände werden nicht gemappt.** `--s1`…`--s7` existieren dreimal mit
verschiedenen Werten — `:root` 4/6/10/16/24/32/48, `[data-theme="portal"]`
–/–/14/20/32/44/64, `[data-theme="crm"]` 4/8/12/16/20/28/32. Ein Mapping auf
`--spacing-*` hätte die kontextabhängige Bedeutung aus der Kaskade in die
Utility-Namen gerettet, und `p-5` hieße je nach Ort 24px, 32px oder 20px. Das
ist genau das Problem, das die Migration beseitigen soll.

Stattdessen Tailwinds eigene Skala. Alle drei Saetze liegen exakt auf dem
4px-Raster (6px = `1.5`, 10px = `2.5`, 14px = `3.5`), es entsteht kein einziger
arbitrary value. Die Umrechnung passiert beim Migrieren eines Screens, der
Screenshot-Diff belegt sie.

Dritte Fehlerquelle, und die teuerste: eine Legacy-Regel, die ueber eine
*Komponente* hinweggreift. `.qpal [data-slot="button"]` setzt Schriftgroesse
und Polsterung der Buttons darin; loescht man `.qpal`, fallen sie auf ihre
Variante zurueck und die Karte wird fuenf Pixel flacher. `.pme button`,
`.formfoot [data-variant="default"]` und `.cardhead [data-variant="ghost"]`
sind dieselbe Sorte — vierzehn Regeln im Repo. Jede muss beim Migrieren ihres
*Kontexts* an den Aufruf wandern, und dort durch `cn()`: bei einem rohen
Element entscheidet sonst wieder die Reihenfolge im erzeugten CSS.

Verwandt: eine Komponentenvariante vererbt Werte, die die abgeloeste Regel
ausdruecklich gesetzt hatte. Die Adresse im Kontakt-Typeahead stand auf 400,
weil `.ta .em` eine `font`-Kurzschrift trug; ohne ein eigenes `font-normal`
erbt sie die 500 der ghost-Variante und wird vier Pixel breiter.

Zweite Fehlerquelle, sechsmal aufgetreten: eine Klasse in der Basis und eine
widersprechende in `:class`. `class="text-[13px]"` neben
`:class="… 'text-[13.5px]'"` — welche gewinnt, entscheidet die Reihenfolge im
erzeugten CSS, nicht die im Attribut. Beide stehen in derselben Layer. Solche
Paare gehoeren gegenseitig ausschliessend geschrieben (`:class="a ? 'x' : 'y'"`)
oder durch `cn()` geschickt.

Die haeufigste Fehlerquelle dabei: dieselbe Nummer meint je Scope etwas
anderes. `--s2` ist im Root 6px und im crm-Scope 8px. Wer die Skala aus dem
Kopf einsetzt statt sie zu messen, ist um zwei Pixel daneben.

**Farben dürfen kontextabhängig bleiben**, weil `bg-paper` "die Papierfarbe
dieser Oberfläche" heißt und nicht ein Hex. Dafür ist `@theme inline` tragend:
Tailwind schreibt `var(--paper)` in die Utility statt den Wert. `app.css`
mappt heute 21 Namen nach diesem Muster.

Fehlende Farbnamen (`paper`, `sunk`, `sunk-2`, `ink-2`, `mark`, `rule`, `ok`,
`due`, `late`) kommen dann dazu, wenn ein Screen sie braucht — nicht vorab. Ein
`@theme`-Eintrag ohne Aufrufer ist dieselbe Spekulation wie eine Komponente
ohne Aufrufer.

Offen und bewusst nicht angefasst: `--radius-md: var(--r)` ist bereits
kontextabhängig (8px root, 10px portal, 20px crm). Das anzugleichen würde jede
shadcn-Kontrolle auf allen Oberflächen umstellen und gehört nicht in diese
Migration.

## §3 Sicherungsnetz

`tests/e2e/shots/admin.mjs` läuft 25 Routen mit Login ab,
`playwright.config.ts` existiert. Umbau zu einem Spec mit
`toHaveScreenshot()` je Route, dazu Portal und Galerie.

Der Beweis, dass das Netz taugt: §1 und §2 müssen pixelgleich durchlaufen.
Beide ändern nur Selektoren und Tokennamen. Zeigt der Diff dort etwas, ist
entweder das Netz zu grob oder eine Annahme falsch.

Erledigt: `tests/e2e/visual.spec.ts`, 23 Routen, drei Läufe identisch. Zwei
Dinge waren nötig, damit das gilt. Der Test braucht sein eigenes Zeitbudget —
die 30 Sekunden aus `playwright.config.ts` sind für einen Flow gedacht, nicht
für 23. Und jeder Screenshot wartet auf `document.fonts.status === 'loaded'`:
`networkidle` landet sonst zwischen Fallback-Paint und Font-Swap, was die
Marke in Instrument Serif um exakt 594 Pixel driften ließ.

Stand: 62 Frames (59 Admin, 3 Portal). Dazu gekommen sind die Zustaende, die
keine Route haben: Kommandopalette, Einladungsdialog, Vorlagen-Editor,
Paketposition, Kalenderliste, fuenf Galerie-Reiter, Kontaktsuche in zwei
Zustaenden, fuenf Projekt-Unterseiten. Drei Screens koennen keine bekommen,
und der Test sagt es statt zu schweigen: `/nachrichten` im Portal antwortet
404, weil der Kontakt Nachrichten abgeschaltet hat, und Projekt- und
Galerieseiten erscheinen erst nach einer Freigabe.

`tests/e2e/shots/cascade.mjs` liest die wirksamen Stile einer Route. Seit es
das gibt, sitzt eine Portierung beim ersten Versuch — davor waren es drei
Anlaeufe pro Screen.

Portal- und Galerie-Baselines fehlen noch. Sie brauchen einen zweiten
Playwright-Server (Portal läuft auf 8001 mit `APP_SURFACE=portal`) und den
Magic-Link-Umweg über Mailpit, den `tests/e2e/shots/portal.mjs` bereits
beschreibt. Sie müssen vor Phase 2 stehen.

## §4 Phasen

| Phase | Umfang | Ergebnis |
|---|---|---|
| 0 | Playwright-Baselines | erledigt: 62 Admin-, 17 Portalframes |
| 1 | Token-Selektor | erledigt, pixelgleich |
| 2 | Admin-Shell: `AdminLayout`, `CommandPalette`, `SettingsNav`, `ProjectHeader` | erledigt |
| 3 | Admin-Screens, absteigend nach Masse | erledigt: `crm.css` traegt nur noch Token |
| 4 | Portal: 18 Dateien | `portal.css` geloescht |
| 5 | Galerie: `Gallery/Show`, `Gallery/Download`, `GalleryLightbox` | `gallery.css` geloescht (342 Zeilen) |
| 6 | Auth-Seiten, Editoren, Reste | Sheets bis auf `:root` geloescht |

## Was den Rest blockiert

Der Test meldet jede Luecke selbst (`OHNE BASELINE: …`). Vollstaendige Liste,
Stand dieser Sitzung — 15 Templates, 32 Klassennamen, 71 Vorkommen:

| Datei | Klassen |
|---|---|
| `Projects/Quotes` | 17 |
| `PaymentInstallmentsEditor` | 7 |
| `Posteingang` (die drei Karten unter dem Faden) | 7 |
| `Projects/Shoots` | 3 |
| `pdf/shoot-timeline.blade.php` | 3 |
| `CrewStack` | 2 |
| `Settings/Numbering`, `DocumentPreviewPane`, `Metric` | je 1–2 |
| `PortalWrap`, `Contracts/Show`, `Quotes/Show`, `ui/field/Field` | `wide` |
| `GalleryLightbox`, `Gallery/Show` | `open`, `cover` |

Nicht in der Liste, weil unfestgeschrieben: `Auswertungen` steht im
Arbeitsbaum bereits auf Utilities um.

Vier Faelle sind keine Restarbeit, sondern haengen an etwas:

`Setup` verlangt ein Studio ohne einen einzigen Mitarbeiterzugang; den
herzustellen hiesse, alle Zugaenge zu loeschen. Die Huelle ist dieselbe wie
bei den neun anderen Auth-Schirmen, das Formular darin ist ungeprueft.

`PaymentInstallmentsEditor` hat keinen Aufrufer. Migrieren waere Arbeit an
totem Code; geloescht habe ich ihn nicht, das ist keine Entscheidung dieser
Migration.

`CrewStack` haengt an Regeln, die anderen Dateien gehoeren: `.card:hover
.stack .av` und `.srow .stack` faerben und ruecken die Gesichter aus dem
Kontext heraus. Solange `Projects/Shoots` und die Kartenlisten stehen, waere
jede Uebersetzung hier eine Wette darauf, was dort spaeter steht.

`wide` traegt in vier Dateien zwei Bedeutungen — `.fld.wide` spannt eine
Spalte, `PortalWrap`s `wide` die Seitenbreite. Wer den einen Namen aufloest,
muss den anderen mitnehmen, sonst bleibt eine Regel ohne Aufrufer stehen.

Fuer die Galerie legt `E2eSeeder` echte Bytes an: `GalleryImageController`
rechnet jede Variante aus dem Original auf der s3-Platte und antwortet ohne
eines 404, also sechs einfarbige JPEGs — ein Foto waere huebscher, und die
Baseline haenge dann an der Version eines JPEG-Encoders.

Kein Frame haengt an den Demodaten eines Entwicklers. Das war vorher der
stille Fehler: `migrate:fresh --seed=E2eSeeder` legt genau die Fixtures an und
sonst nichts, die Baselines waren auf einem CI-Lauf also gar nicht
reproduzierbar.

Teilweise abgedeckt, migriert wird trotzdem: `Payments/Show` (Bankblock und
Stripe-Spalte brauchen ausgefuellte Studioeinstellungen), `Contracts/Show`
(das eingebettete Dokument ist 404, weil kein Artefakt gerendert wurde),
`Questionnaire` (Auswahlfeld, Ja/Nein-Zeile und Fehlermeldung: das Fixture
hat ein Feld, und es ist ein Textfeld).

Drei Stellen sind beim Aufloesen von `crm.css` aufgefallen und bewusst
uebernommen worden, weil die Baseline sie so zeigt:

- `<select class="form">` in `Projects/Show` bekam nie die Feldoptik —
  `.form input` will einen Nachfahren, das Element ist selbst `.form`. Steht
  jetzt als `max-w-[820px]` da, also weiter als nackte OS-Auswahl.
- Die `.line`-Reihen im Shooting-Editor waren nie flex: die Regel heisst
  `.composer .line` / `.editing .line`, der Container traegt `form`. Die
  Felder stapeln.
- Die Ueberschrift in `Leads/Show` misst 32px, nicht die 25px aus
  `.detail .title h1` — die Klasse `.detail` war beim Portieren schon weg,
  gegriffen hat nur noch die Elementregel.

## Namenskollisionen

Vier Klassen heissen in zwei Sheets gleich, und die zweite Regel gewinnt
still. Alle vier haben Pixel gekostet:

| Name | erwartet aus | trifft ausserdem |
|---|---|---|
| `.block` | gallery.css (Abschnitt) | Tailwinds eigene `block`-Utility |
| `.stage` | gallery.css (Bildbuehne) | tokens.css, 20px Rand nach oben |
| `.who` | gallery.css (Kopfzeile) | tokens.css, Schrift und 18px rechts |
| `.where` | portal.css `.pend` | portal.css `.rail`, spaeter und damit staerker |

Die ersten drei bleiben erhalten, wie sie heute aussehen; `.rail .where` war
die richtige Regel und stand nur nicht in der Datei, in der man sie sucht.

## §6 Mockups

`mockups/` bleibt Source of Truth für Designwerte; die leben ab §2 in `@theme`.
Verloren geht die Klassen-Kopie: ein Klickdummy lässt sich dann nicht mehr
zeilenweise gegen die App diffen. Der Kommentar in `tokens.css:5` beschreibt ab
§2 nur noch den Token-Teil und wird gekürzt, nicht gelöscht.

## §7 Fertig heißt

- Screenshot-Baselines grün für alle Routen
- kein `class` mehr, das in einem eigenen Sheet definiert ist
- `vue-tsc --noEmit` und Vite-Build grün
- Pest-Suite grün (`-e APP_SURFACE=admin`)
- kein `@apply` im Ergebnis
- kein Sheet in `@layer components`, solange Legacy-Regeln auf
  `[data-slot=...]` noch ueber den Utilities stehen muessen
- keine Komponente in `resources/js/ui` mit null Verwendungen
- keine extrahierte Komponente mit weniger als drei Aufrufern
- keine Datei ohne Frame migriert; wo kein Frame moeglich ist, sagt der Test
  es (`OHNE BASELINE: …`) statt zu schweigen
