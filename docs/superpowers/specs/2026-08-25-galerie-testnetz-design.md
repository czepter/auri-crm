# Galerie-Testnetz — Portal und Astro-Viewer

Status: Entwurf. Ein Fehler in `GalleryLightbox.vue` ist beim Vermessen
aufgefallen und trägt jetzt §2.
Datum: 2026-08-25

## Ziel

Die zwei Flächen, die ein Kunde von der Galerie zu sehen bekommt, sollen eine
Assertion haben, die bricht, wenn ihre Logik bricht. Heute hat der Astro-Viewer
für seine gesamte Interaktivität keine, und das Portal für seine 3853 Zeilen
eine einzige.

Nicht Ziel: eine Testquote. Von 217 `.vue`-Dateien haben 212 keinen Test, und
das bleibt so — Markup gehört nicht in Vitest.

Nicht im Umfang: `admin/`. Dort sind 20 von 23 Modulen in `admin/lib` getestet;
die drei offenen (`queueQuery`, `search`, `shoots`) sind kein Kundenpfad.

## Ausgangslage

| | |
|---|---|
| Pest | 1967 Tests, 10481 Assertions, 91 s |
| Vitest `apps/studio` | 173 Tests, 27 Dateien, 1,7 s |
| Vitest `apps/gallery` | 43 Tests, 9 Dateien, 0,5 s |
| Playwright | 9 Specs, 11 Tests, 90 Frames (72 Admin, 18 Portal) |
| `admin/lib` mit Nachbartest | 20 von 23 |
| `portal/lib` mit Nachbartest | 1 von 3 |
| `apps/gallery/src` mit Nachbartest | 9 von 10 |
| Portal: Quellzeilen / Testzeilen | 3853 / 51 |
| Kopien der Breitenliste | 4 |
| Implementierungen von `srcSet` | 3 |
| Implementierungen des Index-Umlaufs | 2 |

Zwei Messwerte tragen den Plan:

**`lightbox.ts` hat kein Netz, weder oben noch unten.** Es ist das einzige
Modul in `apps/gallery` ohne Nachbartest und laut eigenem Docblock "the one
hand-written module the gallery viewer loads". Playwright fängt es auch nicht
auf: `playwright.config.ts` startet genau einen Server, `php artisan serve` mit
`APP_SURFACE=admin` auf 8000. Astro bootet in keinem Lauf. 193 Zeilen beim
Kunden, null Assertions.

**Dieselbe Konstante steht viermal, und die Kopien sind schon auseinander.**

| Ort | WIDTHS | Test |
|---|---|---|
| `ImageVariant.php:22` | `400, 800, 1200, 1600, 2400` | Pest |
| `apps/gallery/src/lib/images.ts:11` | `400, 800, 1200, 1600, 2400` | `images.test.ts` |
| `portal/pages/Gallery/Show.vue:169` | `400, 800, 1200, 1600` | keiner |
| `portal/components/GalleryLightbox.vue:125` | `800, 1200, 1600, 2400` | keiner |

Beide Portal-Kopien sind Teilmengen und damit heute korrekt:
`GalleryImageController.php:33` antwortet 422 nur auf Breiten *außerhalb* der
Liste. Der Schaden ist nicht der Ist-Zustand, sondern die Richtung — wer die
PHP-Liste ändert, findet zwei Kopien nicht, und niemand merkt es, bis Kacheln
leer bleiben.

## Entscheidungen

1. **Nicht über App-Grenzen vereinheitlichen.** `images.ts:1-11` hat die
   Frage schon entschieden: ein `packages/*`-Modul, das eine PHP- und eine
   Node-App importieren, ist "real cross-runtime tooling for four numbers and
   three strings". Bleibt so. Zusammengelegt wird nur innerhalb des Portals.
2. **Der Test prüft Teilmenge, nicht Gleichheit.** Eine Fläche darf weniger
   Breiten anbieten; das Grid braucht 2400 nicht. Verboten ist nur, was 422
   auslöst.
3. **Kein jsdom, kein `@vue/test-utils`.** Beide Vitest-Configs stehen auf
   `environment: 'node'`, und `GalleryRail.test.ts` zeigt, wie ohne DOM
   getestet wird: `createSSRApp` plus `renderToString`, Assertion auf den
   HTML-String gegen den echten `de.json`-Katalog. Was so nicht erreichbar
   ist, wird als reine Funktion herausgezogen, statt eine DOM-Abhängigkeit
   dafür einzuziehen.
4. **Herausziehen nur, wo es einen zweiten Aufrufer hat.** Jede Extraktion in
   diesem Plan löst eine bestehende Dopplung auf. Keine entsteht auf Vorrat.

## §1 Die Breitenliste

Neu: `portal/lib/images.ts` mit `WIDTHS`, `FORMATS`, `srcSet(src, format)`.

Die Signatur ist eine andere als die in `apps/gallery`, und das ist kein
Versehen: das Portal bekommt seine `src` fertig vom Server
(`GalleryPasswordController.php:233` baut `/galerie-bilder/{version}/{key}`)
und hängt nur die Query an. Der Astro-Viewer baut die volle Proxy-URL selbst
und trägt den View-Pass mit. Gleiche Breiten, verschiedene Adressen.

`Show.vue` und `GalleryLightbox.vue` importieren, ihre lokalen `WIDTHS` und
`srcSet` fallen weg. Welche Teilmenge welcher Screen anbietet, bleibt am
Screen — der Lightbox braucht 2400, das Grid nicht.

Der Test:

    it('bleibt in der Allow-List, die der Bild-Proxy kennt', …)

liest `ImageVariant::WIDTHS` nicht ein, sondern schreibt sie hin. Ein Test, der
seine Erwartung aus derselben Quelle zieht wie der Code, prüft nichts. Die
Zahlen stehen doppelt, und genau diese Doppelung ist die Assertion.

## §2 Der Tastaturgriff

Beim Vermessen aufgefallen, und der Grund, warum §1 nicht der wichtigste Teil
ist: `apps/gallery/src/lib/lightbox.ts:178-186` gibt Pfeiltasten an das
Kommentarfeld ab, solange es den Fokus hat.

    if (event.key === 'Escape') close()
    if (document.activeElement === commentInput) return
    if (event.key === 'ArrowLeft') step(-1)

`GalleryLightbox.vue:108-113` im Portal hat dieselbe Tastenbindung, hängt sie
per `window.addEventListener` global auf (`:122`) — und kennt diese Abgabe
nicht. Das Feld existiert dort (`:255`, `<Input v-model="draft">`).

Ein Kunde tippt einen Kommentar, drückt Pfeil-links, um einen Tippfehler zu
erreichen: das Bild springt. `sendComment()` liest `asset.value.key` erst beim
Absenden (`:89-92`), der Kommentar landet also am falschen Foto. Der Entwurf
selbst bleibt stehen, was den Fehler still macht.

Der Fix ist die Zusammenlegung aus §1 in einer zweiten Datei, nicht ein
Guard an der Portal-Kopie: `portal/lib/lightbox.ts` mit

    export function keyIntent(key: string, typing: boolean): 'close' | 'prev' | 'next' | null
    export function stepIndex(index: number, delta: number, length: number): number

`keyIntent` trägt beide Regeln — Escape kommt immer durch, Pfeile nur ohne
Fokus im Feld. `stepIndex` ist der Umlauf, der heute zweimal steht. Vier
Tests, einer je Rückgabewert.

Nicht extrahiert: der Fokus-Test selbst. Ob getippt wird, entscheidet die
Komponente (`document.activeElement` im Viewer, ein `ref` im Portal); die
reine Funktion bekommt das Ergebnis als Boolean.

## §3 lightbox.ts

Was in `apps/gallery` bleibt, ist eine Closure aus DOM-Verdrahtung. Testbar
ohne DOM ist daraus dreierlei:

1. Der Umlauf (`:124`) — geht nach `portal/lib` nicht, die Apps teilen keinen
   Code. Zweite kleine Kopie, diesmal mit Test.
2. Die Abgabe an das Kommentarfeld, dieselbe Regel wie §2.
3. **Der Stale-Guard** (`:170-176`): `loadComments` merkt sich den Key, für den
   es geholt hat, und verwirft die Antwort, wenn der Gast inzwischen
   weitergeblättert ist. G-12 nennt das im Kommentar, nichts prüft es. Als
   `isCurrent(requested, current)` ist es ein Vergleich; der Wert liegt darin,
   dass ein Umbau der Reihenfolge in `render()` ihn nicht mehr still
   entfernen kann.

Der Rest der Datei — `renderComments`, die Listener-Registrierung, die
Klassen-Toggles — bleibt ungetestet. Dafür wäre jsdom nötig, und das ist der
Preis aus Entscheidung 3.

## §4 Der falsche Kommentar

`Show.vue:166-168` behauptet, die Liste darunter sei
`App\Gallery\Support\ImageVariant::WIDTHS`, und begründet dann "the two
largest exist for retina rather than for layout". Beides stimmt nicht mehr:
2400 fehlt, und die zwei größten der deklarierten Liste sind 1200 und 1600.

Nach §1 steht dort ein Import. Der Kommentar sagt dann, was er soll: dass das
Grid absichtlich unter der Allow-List bleibt, und warum.

## §5 Was ungetestet bleibt

Beim Namen genannt, damit die Lücke eine Entscheidung ist und kein Versehen:

- **`apps/gallery` hat keinen E2E-Lauf.** Ein zweiter `webServer` in
  `playwright.config.ts` für Astro auf 4321 wäre der Weg. Er hängt an
  Fixtures, die `E2eSeeder` heute nicht baut (ein Gast-Zugang ohne
  Portal-Login), und gehört nicht in diesen Plan.
- **Die 212 `.vue` ohne Test.** Das Portal hängt weiter an
  `visual-portal.spec.ts` — einem Test mit 18 Frames, dessen Baselines
  altern, weil `E2eSeeder` relativ zu `now()` seedet.
- **`Download.vue`.** Durchgelesen: das Polling-`watch` räumt bei Re-Trigger
  und in `onUnmounted` auf, es gibt nichts zu sichern.
- **`portal/lib/studio.ts`, `initials.ts`.** `reachable()` ist ein Einzeiler
  mit drei ODER-Zweigen. Ein Test dafür hebt die Zahl und senkt kein Risiko.

## §6 Fertig heißt

- `portal/lib/images.ts` und `portal/lib/lightbox.ts` existieren, je mit
  Nachbartest; `WIDTHS` und `srcSet` stehen in keiner `.vue` mehr
- Pfeiltasten bewegen das Bild nicht, während der Kommentarentwurf Fokus hat
- `apps/gallery/src/lib/lightbox.ts` hat einen Nachbartest über Umlauf,
  Tastenabgabe und Stale-Guard
- kein neues Paket in einer `package.json`, beide Vitest-Configs weiter auf
  `environment: 'node'`
- `pnpm lint` und `pnpm test` grün, Pest grün (`-e APP_SURFACE=admin`)
- Playwright-Frames unverändert: dieser Plan fasst kein Markup an
