# Galerie-Verwaltung: vollständiger Neuentwurf

Stand 2026-08-23. Auslöser: die Galerie-Verwaltung im Studio soll neu
entworfen werden — Übersicht, Anlegen, Sets, Upload, Einstellungen,
Titelbild. Vorbild ist Pixieset (Screenshots vom Auftraggeber), Optik bleibt
Atelier Nordlicht (`resources/css/tokens.css`).

## Ausgangslage

Zwei Sackgassen tragen den ganzen Bereich, beide entstanden dadurch, dass
Admin und Auslieferung getrennt gebaut wurden.

**Hochgeladene Bilder erreichen die Galerie nie.** Es gibt zwei Ablagen für
dieselbe Sache:

| | `assets`-Tabelle | `galleries.assets` (json) |
|---|---|---|
| geschrieben von | `Media/Actions/IngestCompletedUpload.php` | `Gallery/Actions/ImportLegacyGallery.php:115`, `database/seeders/E2eSeeder.php:341` |
| gelesen von | `Media/Actions/BuildGalleryZip.php`, Bildauslieferung | `Gallery/Http/Controllers/GalleryRecordController.php:51`, `Gallery/Models/Gallery.php:149` |

Der Upload legt `Asset`-Zeilen an. Der Bilder-Reiter und `Gallery::publish()`
lesen die json-Spalte. Nichts verbindet beide. Folge: hochladen und der Reiter
bleibt leer, veröffentlichen und die Version ist leer. `GalleryRowData::
fromModel()` zählt `count($gallery->assets)` und zeigt deshalb in der Liste
überall 0.

**Die Gestaltung ist nicht angeschlossen.** `apps/gallery/src/pages/g/
[galleryId].astro:102` nimmt fest `version.assets[0]` als Cover, das Raster
ist vierspaltig verdrahtet, es gibt ein Farbschema.
`Gallery/Http/Controllers/GalleryReadController.php` liefert `design` nicht
einmal aus. Die drei Achsen aus C-19 (#255) sind Schalter ohne Leitung.

Dazu die Lücken, die der Auftrag benennt:

- **Sets** sind nur ein Schlüssel `set` im Asset-Eintrag
  (`GalleryRecordController.php:98`). Nichts legt eins an, benennt es um oder
  legt Bilder hinein.
- **Titelbild** existiert nicht. Vorschau und Portal nehmen das erste Bild.
- **Kein Veröffentlichen im Admin.** `Gallery::publish()` wird nur aus
  `ImportLegacyGallery.php:124` heraus aufgerufen — kein Controller, keine
  Route. Eine im Studio angelegte Galerie erreicht nie eine Version.
- **Duplikate** werden erst nach dem Upload erkannt
  (`assets_gallery_checksum_unique`), also nachdem die Bytes durch sind.
- **Doppelte Steuerung.** Passwort und PIN stehen in `Record.vue` zweimal auf
  derselben Seite — im Kopf als Feld, im Freigaben-Reiter als Text. Das
  Ablaufdatum steht im Anlegen-Formular und danach nirgends.

## Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Aufbau des Records | Arbeitsfläche mit linker Schiene (Titelbild, vier Icon-Reiter, Set-Liste). Upload wandert hinein, `Ingest.vue` entfällt |
| 2 | Wo leben Sets, Reihenfolge, Titelbild | `assets`-Zeilen werden die Wahrheit. `galleries.assets` fällt weg |
| 3 | Duplikatprüfung | Dateiname + Größe, vor dem Upload |
| 4 | Filter der Übersicht | Status · Projekt · Shootingdatum · Ablauf, dazu Suche, Sortierung, Rasterdichte. Kein „Markiert" |
| 5 | Datum ohne Shooting | `galleries.event_date`, ausgeschlossen per Check-Constraint gegen `shoot_id` |
| 6 | Externe Galerie | Eigener Weg mit eigener Route, nicht als Umschalter im Anlegen-Formular |
| 7 | Umfang der Gestaltung | Zwölf Cover-Aufmachungen, Farbe, Raster, Schriftpaar — im Admin *und* in `apps/gallery` |
| 8 | Veröffentlichen | Jeder Klick eine neue Version, plus Zurücknehmen auf Entwurf |
| 9 | Reihenfolge der Bilder | Nur Sortierregeln pro Set, keine `position` an `assets` |
| 10 | Einstellungsseiten | Studio-Vorgaben und Vorlagen werden beide neu gebaut |

## Datenmodell

### Neu: `gallery_sets`

`tenant_id`, `public_id`, `gallery_id` (FK, cascade), `name`, `position`,
`sort_key` (`taken_at` | `filename`), `sort_dir` (`asc` | `desc`),
Zeitstempel. Unique auf `(tenant_id, gallery_id, name)`, Index auf
`(tenant_id, gallery_id, position)`.

Die Sortierung sitzt am Set, nicht an der Galerie: ein Highlight-Set will
Aufnahmezeit, ein Fotobox-Set den Dateinamen des Automaten.

### `assets`

`gallery_set_id` — nullable FK auf `gallery_sets`, `nullOnDelete`. Ein
gelöschtes Set wirft seine Bilder nicht weg, es entlässt sie ins
Set-lose. Keine `position`-Spalte (Entscheidung 9).

### `galleries`

| Spalte | | |
|---|---|---|
| `cover_asset_id` | neu | nullable FK auf `assets`, `nullOnDelete` |
| `cover_focal_x`, `cover_focal_y` | neu | smallint 0–100, null = Mitte |
| `event_date` | neu | date, nullable |
| `unpublished_at` | neu | timestamptz, nullable |
| `assets` | **entfällt** | ersetzt durch `assets`-Zeilen |

```sql
alter table galleries add constraint galleries_one_date
  check (shoot_id is null or event_date is null)
```

Angezeigtes Datum ist `shoot?->date ?? event_date`. Das Feld erscheint im
Formular erst, wenn kein Projekt und kein Shooting gewählt ist; hängt später
eins dran, wandert das Datum dorthin und die Spalte wird geleert.

### `gallery_versions`

Neu: `design` (jsonb), `cover_key` (string, nullable).

Ohne diese beiden ist ein veröffentlichter Stand nicht unveränderlich — er
liest seine Gestaltung sonst live aus `galleries` und ändert sich
nachträglich. Der Migrationskommentar
(`2026_08_09_003200_create_gallery_versions_table.php`) behauptet
Unveränderlichkeit bereits; heute gilt sie nur für Titel und Bilder.

Der `assets`-Schnappschuss trägt künftig je Eintrag `key`, `set` (Name, nicht
ID — die Version überlebt das Löschen des Sets) und die aufgelöste
Reihenfolge.

### `design`

Schlüssel: `cover` (zwölf Werte) · `colour` (`light` | `dark` | `warm`) ·
`grid` (`wide` | `dense` | `single`) · `font` (`App\Settings\Enums\FontPair`
oder null = Studio-Paar).

Der Schlüssel `type` aus C-19 entfällt: `FontPair` (#234) beantwortet dieselbe
Frage bereits mit einem geschlossenen, lizenzgeprüften Satz und trägt die
Tracking- und Leading-Korrekturen in `tokens.css`.

### Migrationen

Als **neue Dateien**, nicht in bereits gelaufene gefaltet. Genau dieses Falten
hat am 23.08. auf diesem Branch jede bestehende Datenbank auf altem Stand
stehen lassen.

## Backend

### Routen (`routes/admin.php`)

| Methode | Pfad | |
|---|---|---|
| GET | `/galerien` | Übersicht, Filter als Query |
| GET/POST | `/galerien/neu`, `/galerien` | Anlegen |
| GET/POST | `/galerien/extern/neu`, `/galerien/extern` | Externe Galerie |
| GET/PATCH | `/galerien/{gallery}` | Record |
| POST/PATCH/DELETE | `/galerien/{gallery}/sets[/{set}]` | Sets |
| PATCH | `/galerien/{gallery}/sets/reihenfolge` | Sets sortieren |
| POST | `/galerien/{gallery}/bilder/verschieben` | Mehrfachauswahl in ein Set |
| DELETE | `/galerien/{gallery}/bilder` | Mehrfachauswahl löschen |
| PATCH | `/galerien/{gallery}/titelbild` | Bild und Fokus |
| POST | `/galerien/{gallery}/uploads/pruefen` | Duplikate |
| POST | `/galerien/{gallery}/veroeffentlichen` | Version schreiben |
| DELETE | `/galerien/{gallery}/veroeffentlichung` | Zurücknehmen |
| GET | `/galerien/{gallery}/vorschau` | Signierte Entwurfs-URL für den iframe |

`/galerien/{gallery}/medien` entfällt.

### Duplikate

`uploads/pruefen` bekommt `[{filename, size}]` und antwortet mit den Treffern
aus `assets` derselben Galerie (`filename` + `byte_size`). Der Dialog bietet
*Überspringen* und *Ersetzen*.

Ersetzen behält Set-Zugehörigkeit und Titelbild-Rolle der alten Zeile.
Andernfalls verliert das Nachliefern einer korrigierten Datei die gesamte
Einordnung. Sind die Bytes identisch, greift ohnehin
`assets_gallery_checksum_unique` und `IngestCompletedUpload` konvergiert auf
die vorhandene Zeile; nur bei abweichenden Bytes entsteht eine neue, deren
Vorgängerin gelöscht und deren Zuordnung übernommen wird.

### Vorschau

Der iframe zeigt den **Entwurf**. `apps/gallery` rendert heute nur
`GalleryVersion`. `GalleryReadController` bekommt einen Entwurfsmodus hinter
einer aus dem Admin signierten URL. Ohne das zeigt die Vorschau beim Wechsel
einer Cover-Aufmachung weiter den alten Stand.

### Zugang

`EnsureGalleryGate` bekommt den Zweig für `unpublished_at`: eine
zurückgenommene Galerie ist für Gäste geschlossen, ihre Versionen bleiben
stehen.

### Betroffene Stellen beim Wegfall von `galleries.assets`

`GalleryRecordController`, `GalleryIndexController`, `GalleryRowData`,
`Gallery::publish()`, `ImportLegacyGallery`, `E2eSeeder`. Das Portal liest
`version->assets` (`Portal/Http/Controllers/HomeController.php:114,231`,
`ProjectController.php:189`, `GalleryController.php:136`) und bleibt
unberührt — bis auf `HomeController`, der das erste Bild als Cover nimmt und
künftig `cover_key` liest.

## Screens

| Datei | |
|---|---|
| `admin/pages/Galleries/Index.vue` | neu — Kartenraster mit Titelbild, Statuspunkt, Anzahl, Datum; Filterzeile; Suche |
| `admin/pages/Galleries/Create.vue` | neu — Titel, Projekt, Vorlage; Vorlage zeigt, was sie setzt |
| `admin/pages/Galleries/CreateExternal.vue` | neu — Titel, URL, Projekt |
| `admin/pages/Galleries/Record.vue` | Hülle: Kopf mit Statuschip, Vorschau, Veröffentlichen; linke Schiene |
| `admin/pages/Galleries/panes/Images.vue` | Auswahl, Sets, Dropzone |
| `admin/pages/Galleries/panes/Design.vue` | Titelbild, Fokus, Aufmachung, Farbe, Raster, Schrift, iframe |
| `admin/pages/Galleries/panes/Settings.vue` | Zugang, Angebote, Ablauf, Vorlage sichern, Löschen |
| `admin/pages/Galleries/panes/Activity.vue` | drei Blätter plus ZIP-Archive |
| `admin/components/gallery/GalleryRail.vue` | Titelbild, Icon-Reiter, Set-Liste |
| `admin/components/gallery/{DropZone,UploadTray,DuplicateDialog}.vue` | neu |
| `admin/pages/Galleries/Ingest.vue` | **gelöscht** |
| `admin/pages/Settings/Galleries.vue` | neu — Zeilenform wie der ⚙-Reiter |
| `admin/pages/Settings/GalleryPresets.vue` | neu — mit derselben iframe-Vorschau |

Status in der Übersicht ist abgeleitet, keine Spalte: keine Version =
Entwurf · `unpublished_at` gesetzt = zurückgenommen · `expires_at` in unter
14 Tagen = läuft ab · vorbei = abgelaufen.

Formfelder kommen aus `resources/js/ui`. Kein rohes `<input>`.

### Bilder-Reiter

Klick wählt, Shift-Klick eine Strecke, ⌘A alles. Schwebende Auswahlleiste mit
*In Set verschieben* · *Herunterladen* · *Löschen*. Auswahl auf ein Set in der
Schiene ziehen verschiebt sie. Pro Bild ein `…`-Menü: Titelbild, In Set,
Original laden, Löschen.

### Zusammenzüge

Passwort, PIN und E-Mail-Nachweis stehen nur noch im ⚙-Reiter. Das
Ablaufdatum verlässt das Anlegen-Formular und steht dort, wo man es ändert.
„Als Vorlage sichern" verlässt den Gestaltungs-Reiter — eine Vorlage trägt vor
allem Angebote und Fristen. Der Download-Reiter (fertige ZIP-Archive) wandert
zu *Aktivität*: Tatsachen über Vergangenes, keine Einstellung.

## `apps/gallery`

Zwölf Cover-Aufmachungen: Zentriert · Links · Roman · Vintage · Rahmen ·
Streifen · Trenner · Journal · Stempel · Kontur · Klassisch · Ohne. Je ein
Zweig in `<header class="cover">` plus Regeln in `resources/css/gallery.css`,
jede auch auf 390 px belastbar.

Farbe und Raster als `data-`Attribute am Galerie-Thema, das in `tokens.css`
bereits existiert. Schriftpaar über `data-font`, wie
`resources/views/app-portal.blade.php:6` es für das Portal tut.

Die Read-API liefert `design` und `cover_key` aus der Version aus.

Das ist der größte Einzelposten des Umbaus: im Admin ist jede Aufmachung ein
Wert mehr in einer `ChoiceGroup`, in der Auslieferung ein eigenes Layout.

## Etappen

1. **Fundament** — Datenmodell, Sets, Upload im Record, Duplikate,
   Veröffentlichen und Zurücknehmen. Ohne das ist alles Weitere Fassade über
   einer Galerie, die ihre Bilder nicht kennt.
2. **Übersicht, Anlegen, externe Galerie**
3. **Gestaltung** — Titelbild, Fokus, zwölf Cover, Farbe, Raster, Schrift;
   Admin und `apps/gallery`
4. **Einstellungen** — ⚙-Reiter, Studio-Vorgaben, Vorlagen mit Vorschau
5. **Aktivität** — drei Blätter plus die ZIP-Archive

Jede Etappe ist für sich lauffähig und einzeln zusammenführbar.

## Tests

Pest (`apps/studio/tests/Feature/Gallery`) je Action: Set anlegen und löschen
entlässt Bilder, Mehrfachverschieben, Titelbild setzen und Löschen des
Titelbilds, Veröffentlichen schreibt Gestaltung und Cover in die Version,
Zurücknehmen schließt das Gate, Duplikatprüfung findet Name-und-Größe.

Vitest für Auswahl- und Duplikat-Logik in `resources/js/admin/lib`.

Container mit `-e APP_SURFACE=admin`.

## Entwürfe

Die Mockups dieser Sitzung liegen unter
`.superpowers/brainstorm/71703-1787477627/content/` (nicht versioniert):
`bestand.html`, `aufbau.html`, `uebersicht.html`, `anlegen.html`,
`gestaltung.html`, `cover-varianten.html`,
`einstellungen-veroeffentlichen.html`, `bilder.html`.
