# Tailwind-only — Migration der eigenen Stylesheets

Status: entworfen, Phase 0 offen
Datum: 2026-08-19

## Ziel

Ein Entwickler soll Styles lesen können, ohne ein hauseigenes Stylesheet zu
kennen. Heute stehen 484 Klassennamen in sieben Sheets, 298 davon mehrfach
definiert — `.lab` viermal, mit vier verschiedenen Werten. Welcher gilt,
entscheidet der Vorfahre.

Nicht Ziel: Bundle-Größe, Spezifitätskonflikte, Ablösung von `mockups/`.

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

Vier ungescopte Element-Regeln können auf migriertes Markup durchschlagen:
`svg` und `h1` (`tokens.css:95,126`), `h1` (`portal.css:20`). `body`
(`tokens.css:86`) ist gewollter Reset. Sie werden nicht vorab entschärft —
Phase 2 zeigt am Screenshot-Diff, ob sie beißen.

## §2 Token-Schicht

`app.css` mappt 21 Farbnamen, aber keine Abstände — daher die 150 Inline
`style="…var(--s5)…"`. Ergänzt wird das Spacing (`--s1`…`--s7` als
`--spacing-1`…`--spacing-7`), die fehlenden Farben (`paper`, `sunk`, `sunk-2`,
`ink-2`, `mark`, `rule`, `ok`, `due`, `late`), die Radien (`r-sm`, `r-pill`)
und die acht eigenen Breakpoints.

`inline` ist tragend: Tailwind schreibt `var(--paper)` in die Utility statt den
Wert. `bg-paper` liefert damit innerhalb von `data-tokens="crm"` den einen und
außerhalb den anderen Wert. Zwei Paletten ohne einen einzigen
Descendant-Selektor.

## §3 Sicherungsnetz

`tests/e2e/shots/admin.mjs` läuft 25 Routen mit Login ab,
`playwright.config.ts` existiert. Umbau zu einem Spec mit
`toHaveScreenshot()` je Route, dazu Portal und Galerie.

Der Beweis, dass das Netz taugt: §1 und §2 müssen pixelgleich durchlaufen.
Beide ändern nur Selektoren und Tokennamen. Zeigt der Diff dort etwas, ist
entweder das Netz zu grob oder eine Annahme falsch.

## §4 Phasen

| Phase | Umfang | Ergebnis |
|---|---|---|
| 0 | Playwright-Baselines | Netz steht |
| 1 | Token-Selektor + `@theme`-Ausbau | pixelgleich |
| 2 | Galerie: `Gallery/Show`, `Gallery/Download`, `GalleryLightbox` | `gallery.css` gelöscht (342 Zeilen) |
| 3 | Portal: 18 Dateien | Portal Tailwind-only |
| 4 | Admin-Shell: `AdminLayout`, `CommandPalette`, `SettingsNav`, `ProjectHeader` | geteilte Rahmen |
| 5 | Admin-Screens, absteigend nach Masse | `crm.css` gelöscht |
| 6 | Auth-Seiten, Editoren, Reste | Sheets bis auf `:root` gelöscht |

Phase 2 ist die Abbruchstelle: drei Dateien, ein gelöschtes Sheet, danach steht
der Aufwand pro Screen als Messwert statt als Schätzung.

Geteilte Komponenten (`PortalFooter` 13×, `PortalHeader` 12×, `SettingsNav` 8×,
`ProjectHeader` 5×) migrieren in Phase 4, vor den Screens, die sie einbinden.

## §5 Komponenten

Kandidaten, alle in ≥3 Dateien:

| Komponente | löst ab | Dateien | Vork. | zu schlichten |
|---|---|---|---|---|
| `<Field>` | `.fld` `.err` `.wide` | 37 | 308 | — |
| `<Group>` | `.grp` `.hint` | 42 | 175 | `.hint` 3× definiert |
| `<PageHeader>` | `.hrow` `.kick` | 38 | 90 | je 2× definiert |
| `<Section>` | `.dsec` `.dlab` | 18 | 79 | — |
| `Button` (vorhanden) | `.btn` `.btn-2` | 10 | 32 | — |
| `<AuthLayout>` | `.authwrap` `.authcard` `.brand` | 10 | 30 | — |
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
- keine Komponente in `resources/js/ui` mit null Verwendungen
- keine extrahierte Komponente mit weniger als drei Aufrufern
