# S3 · Angebot als Paket sichern, Paket duplizieren

Stand 2026-08-23. Dritte Scheibe aus
[2026-08-23-angebotserstellung-design.md](2026-08-23-angebotserstellung-design.md).
Ausgangslage und Grundentscheidungen stehen dort.

Zwei Wege, die heute fehlen, obwohl beide Richtungen der Kopie schon gebaut
sind: `Package::copyItems()` kopiert aus einem Paket heraus, `ReviseQuote`
kopiert ein Angebot auf sich selbst. Was fehlt, ist der Weg vom Angebot zurück
in den Katalog — und die Kopie eines Pakets neben sich.

Der Anlass ist die Antwort des Auftraggebers, dass fast jedes Angebot frei
gebaut wird: dann entsteht das brauchbare Paket **im** Angebot und nicht
davor.

## Angebot als Paket

### Weg

`POST /angebote/{quote}/als-paket` in den Angebotsblock von
`routes/admin.php`, neben `versenden`. Der Controller ist
`QuoteController::saveAsPackage()`, die Arbeit macht
`App\Catalog\Actions\CreatePackageFromQuote`.

Die Action liegt in `Catalog`, weil sie ein `Package` erzeugt — spiegelbildlich
zu `App\Quotes\Actions\SeedQuoteFromPackage`, die in `Quotes` liegt, weil sie
Angebotspositionen erzeugt. Jede Action wohnt bei dem, was sie schreibt.

### Berechtigung

Zwei Prüfungen, weil zwei Dinge berührt werden:

```php
$this->authorize('manageQuotes', $quote->project);
$this->authorize('create', Package::class);
```

Heute hält jede Rolle beides. Getrennt geprüft wird trotzdem, aus dem Grund,
den `PackagePolicy` selbst nennt: P6/I-01 gibt API-Token gegen einzelne
Fähigkeiten aus, und Angebote schreiben zu dürfen ist nicht dasselbe wie den
Katalog zu füllen.

### Übertragung

Je Position: `title`, `description`, `quantity`, `unit_price`,
`sort_order` aus der Listenposition — dieselbe Abbildung, die
`PackageController::itemRows()` schon schreibt.

Was **nicht** mitgeht:

- **Rabatte.** `package_items` hat die Spalten nicht, und das ist kein
  Versehen: ein Rabatt gehört zum Verhandlungsfall, nicht zur Leistung. Der
  Angebotsrabatt fällt aus demselben Grund weg.
- **Gültigkeit, Nummer, USt.-Schnappschuss.** Eigenschaften eines Dokuments,
  nicht einer Leistung.

Eine Position ohne Preis bleibt ohne Preis. `unit_price` ist in beiden
Tabellen nullable und meint in beiden dasselbe: enthaltene Leistung.

### Bedienung

Knopf „Als Paket sichern" in der Fußzeile des Angebots, Dialog fragt nur den
Namen. Vorbelegt wird nichts — ein Paketname beschreibt das Angebot, nicht
den Kunden, und ein vorgeschlagener Kundenname wäre genau der falsche.

Verfügbar bei **jedem** Status, nicht nur beim Entwurf. Die Action liest nur,
und das brauchbarste Paket ist das angenommene Angebot.

Validierung: `name` erforderlich, `max:255`, wie in
`PackageController::validated()`. Ein Angebot ohne Positionen wird abgewiesen
— ein leeres Paket ist ein Eintrag, der nichts anbietet.

Neu angelegte Pakete sind aktiv; `packages.is_active` hat den Default.

### Audit

`package.created` mit `context: ['name' => …, 'source' => 'quote']`, Subject
ist die `public_id` des Pakets. Kein neues Ereignis im Vokabular: entstanden
ist ein Paket, und woher, steht im Kontext.

## Paket duplizieren

### Weg

`POST /pakete/{package}/duplizieren`, `PackageController::duplicate()`. Die
Route steht bei den übrigen `/pakete`-Routen; sie kollidiert mit keiner, weil
`/pakete/{package}` ein GET ist.

`$this->authorize('create', Package::class)` — es entsteht ein Paket, also
gilt die Regel fürs Anlegen, nicht die fürs Ändern des Originals.

### Kopie

`replicate()` für Name und Beschreibung, `copyItems()` für die Positionen —
die Methode liefert bereits eine getrennte Kopie ohne `id` und ohne
`package_id`, weshalb hier nichts zusätzlich abzutrennen ist.

Der Name bekommt ` (Kopie)` angehängt. `packages` trägt keinen
Unique-Index auf dem Namen, also kollidiert nichts — mehrfaches Duplizieren
ergibt `(Kopie) (Kopie)`. Das ist sichtbar und beim Umbenennen sofort
korrigiert; ein Zähler dagegen zu bauen wäre Maschinerie für einen Fall, der
sich selbst anzeigt.

`is_active` wird vom Original übernommen. Das Duplikat eines stillgelegten
Pakets ist stillgelegt: dupliziert wird, um zu ändern, und die Entscheidung
über die Sichtbarkeit trifft man danach.

Danach Redirect auf `/pakete/{kopie}` — die Bearbeiten-Seite des Duplikats.
Wer dupliziert, will ändern; auf der Liste zu landen hieße, das Duplikat erst
wiederzufinden.

`package.created` mit `context: ['name' => …, 'source' => 'duplicate']`.

### Bedienung

Knopf „Duplizieren" in `Packages/Index.vue` neben „Aktivieren", gleiche Form
(`variant="pill-muted"`).

## Berührte Dateien

| Neu | |
|---|---|
| `app/Catalog/Actions/CreatePackageFromQuote.php` | |
| `tests/Feature/Catalog/PackageFromQuoteTest.php` | |

| Geändert | |
|---|---|
| `app/Quotes/Http/Controllers/QuoteController.php` | `saveAsPackage()` |
| `app/Catalog/Http/Controllers/PackageController.php` | `duplicate()` |
| `routes/admin.php` | zwei Routen |
| `resources/js/admin/components/QuoteTable.vue` | Knopf und Dialog in der Fußzeile |
| `resources/js/admin/pages/Packages/Index.vue` | Knopf |
| `resources/js/locales/de.json` | |

## Tests

Pest (Container mit `-e APP_SURFACE=admin`):

- Sichern überträgt Titel, Beschreibung, Menge und Einzelpreis, hält die
  Reihenfolge und lässt Positions- wie Angebotsrabatt fallen.
- Eine Position ohne Preis bleibt im Paket ohne Preis.
- Ein Angebot ohne Positionen wird abgewiesen; leerer Name wird abgewiesen.
- Auch ein versendetes Angebot lässt sich sichern.
- `package.created` mit `source: quote` beziehungsweise `source: duplicate`.
- Duplizieren erzeugt eigenständige Positionen: eine Änderung am Duplikat
  lässt das Original unverändert, und umgekehrt.
- Das Duplikat eines deaktivierten Pakets ist deaktiviert.

## Beschriftungen

```
quotes.saveAsPackage    Als Paket sichern
quotes.packageName      Name
packages.duplicate      Duplizieren
packages.copySuffix     (Kopie)
```

## Was S3 nicht tut

- Kein Überschreiben eines vorhandenen Pakets aus einem Angebot heraus.
  Sichern legt immer neu an; wer ersetzen will, dupliziert, ändert und
  deaktiviert das alte.
- Keine Kategorien und keine Ordnung über Paketen. Die Liste ist alphabetisch
  und war es bisher auch.
- Kein Rückweg vom Paket in ein bereits versendetes Angebot. Das ist ein
  Nachtrag und gehört zu Q-07, wo `ReviseQuote` schon unverwendet wartet.
