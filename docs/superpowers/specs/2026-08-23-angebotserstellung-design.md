# Angebotserstellung: Tabelle, Bausteine, Übersicht

Stand 2026-08-23. Auslöser: die Angebotserstellung im Studio ist nicht
intuitiv. Gefordert sind zusätzlich das Kopieren von Paketen und das Sichern
eines Angebots als Paket. Die Ansicht in Lead- und Projektübersicht zählt
gleich viel wie der Editor selbst.

Grundlage aller Entscheidungen ist eine Antwort des Auftraggebers: **fast
jedes Angebot wird frei gebaut.** Ein Paket ist ein grober Startpunkt, fast
jede Zeile wird danach angefasst. Damit ist die Tabelle die Hauptsache und
das Paket nur ein Füll-Weg.

## Ausgangslage

`resources/js/admin/pages/Projects/Quotes.vue` (281 Zeilen) trägt heute
Anlegen, Lesen und Bearbeiten auf einer Seite.

| | Stelle | Reibung |
|---|---|---|
| Anlegen | `Quotes.vue:118-166` | Dialog fragt Gültigkeit und Paket, bevor irgendetwas sichtbar ist. Das Paket steht als `Select` mit Namen da — ohne Inhalt, ohne Summe. Man wählt blind |
| Bearbeiten | `QuoteItemsEditor.vue` | Sechs Felder je Position in einem Zweispalter, eine Karte pro Zeile. Zehn Positionen sind ein Bildschirm Scrollen |
| Doppelt | `Quotes.vue:180-260` | Lesetabelle und Formular stehen untereinander auf derselben Karte. Dasselbe Angebot zweimal |
| Summe | `QuoteController::update()` | `recalculateTotal()` läuft erst beim Speichern. Man tippt Preise, ohne die Summe zu sehen |
| Paket | `QuoteController::store():44` | Wird genau einmal eingesetzt, beim Anlegen. Ein zweites Paket nachschieben geht nicht |
| Reihenfolge | `PackageController::itemRows()` | `sort_order` kommt aus der Listenposition — aber kein Bedienelement verschiebt eine Zeile. Die Reihenfolge ist die Eingabereihenfolge, für immer |
| Rabatt | `Quotes.vue`, `QuoteItemsEditor.vue` | Auswahlfeld plus Wertfeld, je Position *und* je Angebot. Zwei Bedienelemente mal *n* |
| Zustand | `Quotes.vue:47-70` | `editForms` entsteht per `Object.fromEntries` über `props.quotes` beim Setup. Ein Partial-Reload, der ein Angebot hinzufügt, lässt die Map veralten |

Dazu vier handgebaute Kopien derselben Positionsliste, drei davon mit
identischen Klassen (`flex-1` / `w-14` / `w-28`):
`Leads/Show.vue:429-459`, `Projects/Show.vue:336-386`,
`Packages/Index.vue:42-56`, `Quotes.vue:180-197`.

Was in der Lead-Übersicht tatsächlich meistens steht, ist keine Liste: solange
kein Angebot existiert, rendert `Leads/Show.vue:460-472` die aktiven Pakete
als Pills mit Name, Anzahl und Summe. Das ist der Zustand, in dem ein Lead die
meiste Zeit lebt.

## Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Form des Editors | Eine Spalte, inline editierbare Tabelle, Live-Summe in der Fußzeile |
| 2 | Wie kommen Paket und Position hinein | Suchzeile unter der letzten Position. Ein Feld, drei Gruppen: Pakete, Positionen, „neu anlegen" |
| 3 | Wiederverwendungs-Einheit | Beides — Paket (ganzer Satz) und Positionsbibliothek (einzelne Zeile) |
| 4 | Woher die Bibliothek kommt | Neues Modell `CatalogItem`, gefüllt per Merken-Häkchen an der Zeile. Kein Pflegescreen |
| 5 | Speichern | Autosave beim Verlassen der Zelle, 600 ms Debounce, bestehendes `PATCH /angebote/{quote}` |
| 6 | Entwurfsansicht | Nur die Tabelle. Lesetabelle bleibt versendeten Angeboten vorbehalten |
| 7 | Anlegen | Dialog entfällt. „Neues Angebot" legt sofort an |
| 8 | Lead-Übersicht | Fassungs-Pills, darunter die drei größten Positionen und die Summe |
| 9 | Projekt-Leistungsumfang | Bleibt volle Liste. Dort ist die Zeile die Sache selbst |
| 10 | Audit bei Autosave | `quote.updated` gedrosselt, nicht gestrichen |

## S1 · Der Tabelleneditor

### Aufbau

`Quotes.vue` verliert den Dialog und den Doppelaufbau. Ein Entwurf ist
ausschließlich `QuoteTable.vue`; `status !== 'draft'` rendert weiter die
heutige Lesetabelle, unverändert.

Spalten: Griff · Position · Menge · Preis · Rabatt · Summe · Entfernen. Die
Beschreibung steht als zweite Zeile in der Positionszelle, nicht als eigene
Spalte. Fußzeile: Gültig bis (`ValidUntilField` unverändert übernommen),
Angebotsrabatt, Summe, Versenden.

`QuoteItemsEditor.vue` entfällt. Sein Kartenstapel ist genau das, was der
Auftrag als „nicht intuitiv" benennt.

Mit dem Dialog verliert `Quotes.vue` auch seine `packages`-Prop und
`ProjectController::quotes()` die Abfrage dahinter. S2 holt sie zurück, dann
als `PackageData[]`. Eine Prop in S1 stehen zu lassen, die nichts rendert,
wäre Gerüst für später.

### Anlegen

„Neues Angebot" postet direkt auf `/auftraege/{project}/angebote` ohne
Nutzlast. `QuoteController::store()` hat den Fallback schon: ohne
`valid_until` greift `StudioSettingsResolver::quoteValidityDays()`. Der
`package`-Zweig bleibt bestehen — er behält in S1 einen Aufrufer, siehe
Lead-Pills.

### Autosave

Blur auf einer Zelle stößt einen Debounce von 600 ms an, danach geht ein
`router.patch` auf `/angebote/{quote}` mit dem vollständigen Positions-Array
— genau die Nutzlast, die `QuoteController::update()` heute schon erwartet.
Die Antwort ist ein Partial-Reload auf `quotes`, damit die serverseitig
gerechneten Summen zurückkommen. Solange der Request läuft, zeigt die
Fußzeile die im Client gerechnete Summe.

Das wholesale delete-and-recreate in `update()` bleibt, wie es ist: keine ID
zeigt auf eine `quote_items`-Zeile, deshalb verwaist nichts.

`editForms` als Map über `props.quotes` fällt damit weg; der Zustand hängt an
der Komponente, die eine Zeile rendert.

### Rabatt als eine Zelle

Statt Auswahlfeld plus Wertfeld ein Textfeld. `10%` ergibt
`discount_type: percentage`, `50` ergibt `amount`, leer ergibt `null`. Parser
und Formatierer nach `resources/js/admin/lib/money.ts`, neben
`percentToBasisPoints()` und `eurosToCents()`, die die Umrechnung schon
tragen. Gilt gleichermaßen für den Angebotsrabatt in der Fußzeile.

### Reihenfolge

Der Griff ist ein `<button>`: HTML5-`draggable` für die Maus, `ArrowUp` und
`ArrowDown` bei Fokus für die Tastatur. Keine neue Abhängigkeit — im Baum
liegt keine Sortier-Bibliothek, und `@vueuse/core` bringt `useSortable` ohne
`sortablejs` nicht mit. `sort_order` kommt weiter aus der Array-Position.

### Übersichts-Block

Neu `admin/components/QuoteSummary.vue`, verwendet in `Leads/Show.vue`:

- **Kein Angebot** — unverändert die heutigen Paket-Pills. Neu ist nur das
  Ziel des Klicks: statt den Reiter zu öffnen, legt er das Angebot an und
  füllt es mit dem Paket (`POST` mit `package`, der bestehende Weg durch
  `SeedQuoteFromPackage`).
- **Angebot vorhanden** — eine Pill je Fassung: aktuelle Fassung mit Status
  und Summe, ersetzte Fassungen gedimmt mit durchgestrichener Summe. Darunter
  die drei teuersten Positionen und `+ n weitere`, dann die Summe.

Ein Entwurf trägt keine Nummer — `SendQuote` vergibt sie erst beim Versenden.
Die Pill beschriftet sich deshalb aus `number ?? $t('quotes.draft')`.

`LeadCaseController` liefert heute nur `latestQuote`. Für die ersetzten
Fassungen kommt eine schmale Liste dazu: `id`, `number`, `total` je
superseded Quote des Projekts.

Die Positionsliste selbst wird `admin/components/LineItems.vue` und ersetzt
die drei identischen Kopien in `Leads/Show.vue`, `Packages/Index.vue` und der
Lesetabelle in `Quotes.vue`. `Projects/Show.vue` nutzt sie mit einem Slot für
Zustandsmarkierung und Differenz.

Der Baustein stellt beide nur dar und führt sie nicht zusammen: `scope` folgt
der Wirklichkeit und darf vom Angebot abweichen — `Projects/Show.vue:336`
sagt das ausdrücklich —, `quote.items` ist ein eingefrorenes Dokument. Genau
diese Unterscheidung ist der Grund, aus dem es die Differenz gibt.

### Audit

`AuditLogger` bekommt `recordThrottled(..., within: CarbonInterval)`: schreibt
nur, wenn zu `event` und `subject` in diesem Fenster noch nichts steht.
`QuoteController::update()` nutzt es mit 15 Minuten. `quote.created` und
`quote.sent` bleiben ungedrosselt.

## S2 · Bausteine

### Modell

`App\Catalog\Models\CatalogItem` — `BelongsToTenant`, `HasPublicId`, dazu
`title`, `description`, `unit_price`. Unique auf (`tenant_id`, `title`).
`CatalogItemData` für die Prop.

Kein CRUD-Screen. Die Bibliothek füllt sich über das Häkchen.

### Merken

Jede Zeile trägt eine Checkbox. Angehakt heißt: es gibt einen `CatalogItem`
mit diesem Titel. Das PATCH-Payload bekommt `items.*.remember`; der Controller
legt an beziehungsweise löscht nach Titel.

Kein Fremdschlüssel auf `quote_items`, keine Migration dort. Das Häkchen ist
abgeleitet, nicht gespeichert — womit es auch keinen Zustand geben kann, der
mit der Bibliothek auseinanderläuft. Zurückgeschrieben werden `unit_price` und
`description`; Menge und Rabatt gehören dem Angebot.

### Suchzeile

`resources/js/ui/combobox` fehlt und wird lokal auf den `reka-ui`-Primitiven
gebaut, wie `ui/select` es vormacht — die konfigurierte Registry führt keine
Combobox. Gefiltert wird im Client über die Props
`packages` (jetzt `PackageData[]` statt `{value,label}[]`) und `catalogItems`.
Kein Suchendpunkt: die Mengen sind zweistellig.

Drei Gruppen: **Pakete** (Name, Anzahl, Summe), **Positionen** (Titel, Preis),
**Neu** — die Eingabe selbst als freie Position. Enter fügt ein, der Fokus
springt in die neue Titelzelle.

Ein Paket einzufügen hängt die Kopien seiner Positionen an das lokale Array
an, danach greift der normale Autosave. Kein Backend: `update()` ersetzt die
Positionen ohnehin vollständig.

Damit steht das Kopieren eines Pakets an zwei Stellen — in PHP für den Weg
über die Lead-Pill (`SeedQuoteFromPackage`), im Client für das Nachschieben.
Beide haben einen echten Aufrufer. Der Alternativweg wäre eine dritte Route,
die nur weiterreicht.

## S3 · Sichern und Duplizieren

**Angebot als Paket.** `POST /angebote/{quote}/als-paket`, Action
`CreatePackageFromQuote`. Der Dialog fragt nur den Namen. Rabatte fallen
weg — `package_items` hat die Spalten nicht, und ein Rabatt gehört zum
Verhandlungsfall, nicht zur Leistung.

**Paket duplizieren.** `POST /pakete/{package}/duplizieren` in
`PackageController`: `replicate()` plus `copyItems()`, Name mit angehängtem
`(Kopie)`, Redirect auf dessen Bearbeiten-Seite. Knopf in `Packages/Index.vue`
neben Aktivieren.

## Was unverändert bleibt

- `Package::copyItems()` liefert weiter eine getrennte Kopie ohne `id` und
  ohne `package_id`. Ein Paket ist ein Startpunkt, nie eine lebende
  Verbindung.
- `Quote::booted()` bewacht weiter die Unveränderlichkeit versendeter
  Angebote. Der Editor erscheint nur bei `status === 'draft'`; die Regel
  selbst hängt nicht am Bildschirm.
- `ReviseQuote` bleibt unverwendet. Nachträge sind Q-07 und stehen hier nicht
  zur Debatte.
- `scope` im Projekt bleibt eine eigene Datenquelle.

## Tests

Pest (`apps/studio/tests`, Container mit `-e APP_SURFACE=admin`):

- `update()` erhält `sort_order` aus der Array-Reihenfolge über mehrere
  aufeinanderfolgende Autosave-Requests.
- `recordThrottled` schreibt den zweiten Aufruf im Fenster nicht und den
  Aufruf nach Ablauf wieder.
- `items.*.remember` legt einen `CatalogItem` an, das entfernte Häkchen
  löscht ihn, ein zweites Angebot mit demselben Titel legt keinen zweiten an.
- `CreatePackageFromQuote` überträgt Titel, Beschreibung, Menge, Einzelpreis
  und lässt Rabatte fallen.
- Paket duplizieren erzeugt eigenständige Positionen; eine Änderung am
  Duplikat lässt das Original stehen.

Vitest: nur Logik ohne DOM — die Umgebung ist auf `node` gestellt.

- Rabatt-Parser: `10%`, `10 %`, `50`, `50,00`, leer, Unsinn.
- Paket-Einfügen hängt an und überschreibt nicht.
- Filterung und Gruppierung der Suchzeile.

Die e2e-Baselines der betroffenen Seiten werden rot und müssen neu
aufgenommen werden.

## Deutsche Beschriftungen

Neu in `de.json`, kurz gehalten wie die Nachbarn:

```
quotes.draft            Entwurf
quotes.insert           Position oder Paket
quotes.insertNew        „{term}" anlegen
quotes.remember         Merken
quotes.more             + {count} weitere
quotes.saveAsPackage    Als Paket sichern
packages.duplicate      Duplizieren
packages.copySuffix     (Kopie)
```

`quotes.discountType`, `quotes.discountNone`, `quotes.discountPercentage`,
`quotes.discountAmount` und `quotes.discountValue` entfallen mit dem
Auswahlfeld.

## Schnitt

| | Inhalt | Neu im Backend |
|---|---|---|
| S1 | Tabelle, Autosave, Rabattzelle, Sortieren, Dialog weg, `LineItems` und `QuoteSummary` | `recordThrottled`, ersetzte Fassungen in `LeadCaseController` |
| S2 | Suchzeile, Paket einfügen, `CatalogItem` und Merken | Modell, Migration, Prop, `remember` in `update()` |
| S3 | Als Paket sichern, Paket duplizieren | zwei Routen, eine Action |

Drei Spezifikationsschnitte, drei Pläne. S1 löst den Auftrag „nicht intuitiv"
für sich allein; S2 und S3 sind das zusätzlich Verlangte.
