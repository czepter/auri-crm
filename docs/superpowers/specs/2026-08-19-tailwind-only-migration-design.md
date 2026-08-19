# Tailwind-only — Migration der eigenen Stylesheets

Status: 29 von 91 Dateien migriert, 630 von 2032 Klassenvorkommen
Datum: 2026-08-19

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
Arguments, und ein blankes `:not([data-tokens] h1)` ueberholt
`[data-theme="crm"] h1` — womit jede unportierte Seite ihre Ueberschrift
verliert.

Verworfen: die Sheets in `@layer components` legen. Das waere der saubere
Weg, kippt aber vierzehn Regeln, die shadcn-Komponenten absichtlich ueber
`[data-slot=...]` ueberschreiben — `.formfoot [data-slot="button"]
[data-variant="default"] { margin-left: 0 }` und Verwandte. Gemessen an den
Baselines: sechs Formularseiten verschoben ihre Buttons.

Erledigt in `crm.css:35`. Dieselben 23 Baselines laufen mit und ohne den
zweiten Selektor grün durch.

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
| 0 | Playwright-Baselines | Netz steht (Admin erledigt, Portal offen) |
| 1 | Token-Selektor | erledigt, pixelgleich |
| 2 | Admin-Shell: `AdminLayout` (erledigt), `CommandPalette`, `SettingsNav`, `ProjectHeader` | geteilte Rahmen |
| 3 | Admin-Screens, absteigend nach Masse | `crm.css` geloescht |
| 4 | Portal: 18 Dateien | `portal.css` geloescht |
| 5 | Galerie: `Gallery/Show`, `Gallery/Download`, `GalleryLightbox` | `gallery.css` geloescht (342 Zeilen) |
| 6 | Auth-Seiten, Editoren, Reste | Sheets bis auf `:root` geloescht |

59 Prozent der verbleibenden Arbeit (830 Vorkommen in 25 Dateien) liegen in
Dateien mit unfestgeschriebener Arbeit — darunter `Projects/Show`,
`Leads/Show`, `Posteingang`, `Uebersicht` und fuenf Einstellungsseiten. Sie
bleiben liegen, bis diese Arbeit committet ist.

Reihenfolge geaendert. Die Galerie sollte zuerst kommen, weil sie am
kleinsten ist. Sie hat aber keine Baseline: Galerie- und Projektseiten
erscheinen im Portal erst, wenn das Studio sie freigegeben hat, und eine
Freigabe aus dem Test heraus wuerde fremde Entwicklerdaten umschreiben. Der
Admin ist mit 24 Routen vollstaendig abgedeckt, also faengt die Migration
dort an.

`AdminLayout` ist die Abbruchstelle: eine Datei, pixelgleich, danach steht
der Aufwand pro Screen als Messwert statt als Schaetzung.

Geteilte Komponenten (`PortalFooter` 13×, `PortalHeader` 12×, `SettingsNav` 8×,
`ProjectHeader` 5×) migrieren in Phase 4, vor den Screens, die sie einbinden.

## §5 Komponenten

Kandidaten, alle in ≥3 Dateien:

| Komponente | löst ab | Dateien | Vork. | zu schlichten |
|---|---|---|---|---|
| `<Field>` | `.fld` `.err` `.wide` | 37 | 308 | erledigt, `ui/field` |
| `<Group>` | `.grp` `.hint` | 42 | 175 | `.hint` 3× definiert |
| `<PageHeader>` | `.hrow` `.kick` | 38 | 90 | je 2× definiert |
| `<Section>` | `.dsec` `.dlab` | 18 | 79 | — |
| `Button` (vorhanden) | `.btn` `.btn-2` | 10 | 32 | — |
| `<AuthLayout>` | `.authwrap` `.authcard` `.brand` | 10 | 30 | erledigt, 4 von 10 Schirmen |
| `<FormFooter>` | `.formfoot` | 16 | 24 | 2× definiert |
| `<Chip>` | `.chip` | 16 | 23 | 3× definiert |
| `<PageWrap>` | `.pwrap` | 10 | 10 | — |
| `<LinkCard>` | `.card` `.cardhead` | 17 | 51 | ≠ Board-Karte |
| `<BoardCard>` | `.cols .card` `.nm` `.mt` `.w` | 2 | — | ≠ shadcn Card |

Zielform, heute in `ProfileForm.vue`:

    <div class="fld wide">
      <Label for="p-firma">…</Label>
      <Input id="p-firma" v-model="form.legal_name" type="text" />
      <p v-if="form.errors.legal_name" class="err">…</p>
    </div>

danach:

    <Field wide :label="…" :error="form.errors.legal_name" v-slot="{ id }">
      <Input :id v-model="form.legal_name" type="text" />
    </Field>

`Label`, `Input`, `Select`, `Textarea`, `Checkbox` liegen in `ui/`. `cva`,
`tailwind-merge` und `clsx` sind installiert; das Varianten-Muster steht in
`ui/button/Button.vue`. Keine neue Abhängigkeit.

**Extraktion beim dritten Screen**, der ein Muster braucht. Die ersten zwei
tragen rohe Utilities. Jede Komponente muss zuerst ihren Namenskonflikt
schlichten — `.formfoot` existiert zweimal, `.chip` dreimal — und genau dort
entstehen die Pixel-Diffs, die Phase 0 sichtbar macht.

Erwartbarer Anfall: Phase 3 `<PageWrap>` `<Section>` `<Group>` `<Chip>`,
Phase 4 `<PageHeader>`, Phase 5 `<Field>` `<FormFooter>` `<LinkCard>`
`<BoardCard>` und der `Button`-Rest, Phase 6 `<AuthLayout>`.

Die 204 Klassen aus genau einer Datei werden nie Komponente, sondern immer
Inline-Utilities.

### Offen: das Feld des Portals

`portal.css .field` ist nicht dasselbe Muster wie `.fld`. Es umschliesst sein
Steuerelement (`<Label class="field"><span>Beschriftung</span><Input/></Label>`)
und stylt das `<input>` darin um — Breite, Polsterung, Rahmen, Radius. Ein
migrierter Aufruf verliert diese Umstellung, weil das shadcn-`Input` dann seine
eigenen Werte behaelt. Das ist eine zweite Feldkomponente, keine Variante der
ersten, und sie gehoert entschieden, bevor die 18 Portalseiten drankommen.

### Zwei Aufräumentscheidungen

`resources/js/ui/card/` löschen — sieben Dateien, null Verwendungen, während
`class="card"` in 17 Dateien steht. Die drei sind nicht dasselbe:
`[data-theme="crm"] .cols .card` ist eine ziehbare Board-Karte,
`tokens.css:161 .card` eine Link-Kachel, shadcns `Card` ein Container. Ein
CLI-Aufruf holt die shadcn-Variante zurück, falls sie gebraucht wird.

`.btn`/`.btn-2` gehen auf das vorhandene `Button` — 55 Dateien nutzen es
bereits, die zehn übrigen sind Nachzügler.

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
