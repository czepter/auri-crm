# S2 · Bausteine: Suchzeile, Positionsbibliothek, Paket nachschieben

Stand 2026-08-23. Zweite Scheibe aus
[2026-08-23-angebotserstellung-design.md](2026-08-23-angebotserstellung-design.md).
Ausgangslage und die zehn Grundentscheidungen stehen dort; dieses Dokument
schneidet nur die Bausteine auf.

S1 hinterlässt eine Tabelle, in die man Zeilen tippt. S2 gibt ihr die zweite
Eingabeart: bereits vorhandene Zeilen und ganze Pakete einfügen, ohne das
Angebot zu verlassen.

## Was S2 löst

| Heute | Nach S2 |
|---|---|
| Ein Paket, einmal, beim Anlegen (`QuoteController::store():44`) | Beliebig viele Pakete, jederzeit, ins offene Angebot |
| Wiederkehrende Einzelleistungen werden jedes Mal getippt | Einmal merken, danach zwei Anschläge |
| Die Paketauswahl zeigt nur Namen (`Quotes.vue:141-153`) | Name, Anzahl, Summe — und die Positionen landen sichtbar in der Tabelle |

## Modell

### Migration

`catalog_items` folgt `package_items` (2026_08_09_001800) ohne dessen
Zugehörigkeit und ohne Reihenfolge — eine Bibliothekszeile gehört zu nichts
und steht in keiner Ordnung:

```php
Schema::create('catalog_items', function (Blueprint $table) {
    $table->id();
    $table->tenantId();
    $table->publicId();
    $table->string('title');
    $table->text('description')->nullable();
    $table->unsignedInteger('unit_price')->nullable();
    $table->timestampsTz();

    $table->unique(['tenant_id', 'title']);
});
```

`unit_price` bleibt nullable und behält dieselbe Bedeutung wie in
`package_items`: keine Zahl heißt enthaltene Leistung, nicht null Euro.

Der Unique-Index über (`tenant_id`, `title`) ist die Regel selbst, nicht ihre
Absicherung: das Häkchen an einer Zeile *ist* die Aussage „diesen Titel gibt
es in der Bibliothek", und zwei Zeilen mit demselben Titel sind dieselbe
Aussage. Die Datenbank hält das, nicht der Controller.

### Modell und Data

`App\Catalog\Models\CatalogItem` mit `BelongsToTenant` und `HasPublicId`,
`$fillable` aus `title`, `description`, `unit_price`. Keine Beziehungen —
nichts zeigt hierher, und von hier zeigt nichts weg. Das ist derselbe Schnitt,
den `Package::copyItems()` schon zieht.

`App\Catalog\Data\CatalogItemData` mit `#[TypeScript]`, damit
`App.Catalog.Data.CatalogItemData` in den Props auftaucht.

Kein Verlauf, kein `last_used_at`, keine Verwendungszählung. Eine Zeile hat
einen Preis; welcher Preis wann galt, steht in den Angeboten.

## Merken

Jede Tabellenzeile bekommt eine `Checkbox` aus `ui/checkbox`. Angehakt heißt:
es gibt einen `CatalogItem` mit diesem Titel.

Der Zustand ist **abgeleitet, nicht gespeichert**. Keine Spalte auf
`quote_items`, keine Migration dort, kein Fremdschlüssel. Damit kann es keinen
Zustand geben, der mit der Bibliothek auseinanderläuft — die Frage „ist diese
Zeile gemerkt" hat genau eine Quelle.

Das PATCH-Payload von `QuoteController::update()` bekommt
`items.*.remember` als `boolean`. Beim Speichern:

- `true` und noch nicht vorhanden → anlegen aus Titel, Beschreibung,
  Einzelpreis.
- `true` und vorhanden → `unit_price` und `description` aktualisieren.
- `false` und vorhanden → löschen.

Zurückgeschrieben werden Einzelpreis und Beschreibung — die beiden Felder, die
eine Bibliothekszeile überhaupt hat. Menge und Rabatt gehören dem
Verhandlungsfall, nicht der Leistung; dieselbe Grenze, die `package_items`
zieht, indem es die Rabattspalten gar nicht erst hat.

### Berechtigung

Der Autosave läuft unter `manageQuotes` auf dem Projekt. Die Bibliothek ist
Katalog, und dafür gibt es `Capability::ManagePackages`. Heute hält jede Rolle
beide — aber `PackagePolicy` begründet ausdrücklich, warum die Fähigkeit
trotzdem im Vokabular steht: P6/I-01 gibt API-Token gegen diese Werte aus, und
ein Token, das Angebote schreiben darf, ist nicht dasselbe wie eines, das den
Katalog ändern darf.

Also beides: das Häkchen wird nur gerendert, wenn die Prop
`canManageCatalog` es zulässt, und `update()` weist einen Request zurück, der
`remember` gesetzt mitbringt, ohne dass der Handelnde die Fähigkeit hält. Kein
stilles Verwerfen — ein Häkchen, das nichts tut, ist schlimmer als ein Fehler.

## Suchzeile

### Baustein

`resources/js/ui/combobox` gibt es noch nicht, und die in `components.json`
konfigurierte Registry führt keinen solchen Eintrag. Der Wrapper wird lokal
auf den `reka-ui`-Primitiven gebaut (`ComboboxRoot`, `ComboboxInput`,
`ComboboxContent`, `ComboboxGroup`, `ComboboxLabel`, `ComboboxItem`,
`ComboboxEmpty`), nach demselben Muster wie `ui/select`: `defineProps` über
den Reka-Typ, `reactiveOmit` für `class`, `useForwardProps`, `data-slot` am
Element, `cn()` für die Klassen.

### Daten

`ProjectController::quotes()` liefert zwei Props zurück beziehungsweise neu:

- `packages` — in S1 entfallen, kommt hier als `PackageData[]` wieder. Die
  Data-Klasse trägt `itemCount` und die vollständigen `items`, damit die
  Combobox Anzahl und Summe zeigen kann, ohne nachzuladen.
- `catalogItems` — `CatalogItemData[]`, alphabetisch.

Kein Suchendpunkt. Ein Studio führt zweistellige Mengen an Paketen und
Bibliothekszeilen; das ist eine Prop, kein Request pro Anschlag.

### Verhalten

Drei Gruppen in fester Reihenfolge:

| Gruppe | Zeile zeigt | Enter bewirkt |
|---|---|---|
| Pakete | Name, Anzahl, Summe | hängt alle Positionen des Pakets an |
| Positionen | Titel, Einzelpreis | hängt eine Zeile an |
| Neu | die Eingabe selbst | hängt eine leere Zeile mit diesem Titel an |

Gefiltert wird gegen den Titel, ohne Unschärfe-Bibliothek: Treffer am
Wortanfang stehen über Treffern in der Mitte. „Neu" steht immer da, auch bei
Treffern — der Titel, den man tippt, kann derselbe sein und trotzdem eine
andere Position meinen.

Nach dem Einfügen springt der Fokus in die Titelzelle der ersten neuen Zeile
und die Suchzeile leert sich. Bei einem Paket springt er in die erste
eingefügte Zeile, nicht in die letzte.

### Paket einfügen

Die Positionen des Pakets werden als Kopien an das lokale Array gehängt,
danach greift der Autosave aus S1 — `update()` ersetzt die Positionen
ohnehin vollständig. Kein Endpunkt, keine Action, keine Route.

Damit steht das Kopieren eines Pakets an zwei Stellen: in PHP für den Weg über
die Lead-Pill (`SeedQuoteFromPackage`, in S1 erhalten), im Client für das
Nachschieben. Beide haben einen echten Aufrufer. Die Alternative wäre eine
dritte Route, die nur weiterreicht, damit eine Kopierschleife an einem Ort
steht — mehr Teile für weniger.

Ein deaktiviertes Paket taucht in der Combobox nicht auf; die Prop kommt aus
`Package::active()`, wie die Auswahl es heute schon tut.

## Berührte Dateien

| Neu | |
|---|---|
| `database/migrations/…_create_catalog_items_table.php` | |
| `app/Catalog/Models/CatalogItem.php` | |
| `app/Catalog/Data/CatalogItemData.php` | |
| `resources/js/ui/combobox/` | Root, Input, Content, Group, Label, Item, Empty, `index.ts` |
| `resources/js/admin/components/QuoteInsertRow.vue` | |
| `resources/js/admin/lib/insert.ts` | Filterung, Gruppierung, Anhängen |

| Geändert | |
|---|---|
| `app/Quotes/Http/Controllers/QuoteController.php` | `remember` validieren, upserten, Fähigkeit prüfen |
| `app/Crm/Http/Controllers/ProjectController.php` | `packages` als `PackageData[]`, `catalogItems`, `canManageCatalog` |
| `resources/js/admin/components/QuoteTable.vue` | Merken-Spalte, Suchzeile unter der letzten Position |
| `resources/js/locales/de.json` | |

## Tests

Pest (Container mit `-e APP_SURFACE=admin`):

- `remember: true` legt einen `CatalogItem` an; ein zweites Angebot mit
  demselben Titel legt keinen zweiten an.
- `remember: true` an einer vorhandenen Zeile aktualisiert `unit_price`, lässt
  Menge und Rabatt der Bibliothekszeile unberührt (sie hat keine).
- `remember: false` löscht die Zeile aus der Bibliothek.
- Ein Handelnder ohne `ManagePackages` bekommt bei gesetztem `remember` einen
  403, und die Bibliothek bleibt leer.
- `catalogItems` und `packages` stehen als Props auf `Projects/Quotes`,
  deaktivierte Pakete nicht.

Vitest — die Umgebung ist auf `node` gestellt, also nur Logik ohne DOM:

- Filterung: Wortanfang vor Wortmitte, Groß- und Kleinschreibung egal.
- Gruppierung: „Neu" auch bei Treffern vorhanden.
- Paket anhängen hängt an und überschreibt nicht; Reihenfolge des Pakets
  bleibt erhalten.

## Beschriftungen

```
quotes.insert        Position oder Paket
quotes.insertNew     „{term}" anlegen
quotes.remember      Merken
quotes.groupPackages Pakete
quotes.groupItems    Positionen
quotes.groupNew      Neu
```

## Was S2 nicht tut

- Kein Pflegescreen für die Bibliothek. Sie füllt sich über das Häkchen und
  leert sich darüber; ein zweiter Ort, an dem Preise veralten, entsteht nicht.
- Keine Preishistorie und keine Verwendungszählung.
- Keine Unschärfesuche als Abhängigkeit.
- Kein Fremdschlüssel zwischen `quote_items` und `catalog_items`. Ein
  Bibliothekseintrag ist ein Startpunkt, nie eine lebende Verbindung — dieselbe
  Regel, die `Package::copyItems()` trägt.
