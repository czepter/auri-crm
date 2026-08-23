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
- **Kein Weg, ein Bild anzusehen.** Der Bilder-Reiter zeigt Kacheln und sonst
  nichts.

## Entscheidungen

### Aufbau

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Aufbau des Records | Arbeitsfläche mit linker Schiene (Titelbild, vier Icon-Reiter, Set-Liste). Upload wandert hinein, `Ingest.vue` entfällt |
| 2 | Wo leben Sets, Reihenfolge, Titelbild | `assets`-Zeilen werden die Wahrheit. `galleries.assets` fällt weg |
| 3 | Filter der Übersicht | Status · Projekt · Shootingdatum · Ablauf, dazu Suche, Sortierung, Rasterdichte. Kein „Markiert" |
| 4 | Externe Galerie | Eigener schmaler Record: Titel, URL, Projekt. Keine Schiene, keine Reiter |
| 5 | Umfang der Gestaltung | Zwölf Cover-Aufmachungen, Farbe, Raster, Schriftpaar — im Admin *und* in `apps/gallery` |
| 6 | Reihenfolge der Bilder | Nur Sortierregeln pro Set, keine `position` an `assets` |
| 7 | Einstellungsseiten | Studio-Vorgaben und Vorlagen werden beide neu gebaut |
| 8 | Studio-weite Gestaltungs-Vorgaben | Keine. Stattdessen `gallery_presets.is_default` — die markierte Vorlage ist beim Anlegen vorgewählt |
| 9 | Schmale Schirme | Kein responsiver Umbau. Übersicht und Record-Kopf bleiben auf dem iPad lesbar, die drei Spalten des Records nicht |

### Daten

| # | Frage | Entscheidung |
|---|---|---|
| 10 | Datum ohne Shooting | `galleries.event_date`, ausgeschlossen per Check-Constraint gegen `shoot_id`. Die Action, die `shoot_id` setzt, leert die Spalte |
| 11 | Migration der `galleries.assets` | Keine Rückfüllung. Spalte fällt, bestehende Entwurfslisten gehen verloren, veröffentlichte Versionen bleiben unberührt |
| 12 | Bild aus dem Entwurf entfernen | `assets.removed_from_draft_at`, **kein** globaler Scope. Zeile und S3-Objekt bleiben |
| 13 | Standard-Set | Jede Galerie bekommt beim Anlegen ein Set „Highlights". `gallery_set_id` ist `not null` |
| 14 | Set löschen | Dialog: Bilder in ein anderes Set verschieben oder mitlöschen |
| 15 | Fokuspunkt beim Bildwechsel | Fällt auf null zurück |
| 16 | Sortierung ohne EXIF | `order by taken_at asc nulls last, filename asc` |
| 17 | Rasterdichte | Tabelle `gallery_grid_prefs` je Mandant, Nutzer und Set. Stufen klein / mittel / groß = 10 / 7 / 4 Spalten |
| 18 | Vorlagenumfang | Zusätzlich Cover, Farbe, Raster, Schriftpaar, `is_default`. Keine Set-Struktur, `design` bleibt ganz-oder-gar-nicht |

### Verhalten

| # | Frage | Entscheidung |
|---|---|---|
| 19 | Duplikatprüfung | Dateiname + Größe, vor dem Upload, galerieweit |
| 20 | „Ersetzen" beim Duplikat | Neue Zeile erbt Set und Titelbild-Rolle, alte bekommt `removed_from_draft_at`. Kein Überschreiben eines Keys |
| 21 | Veröffentlichen | Jeder Klick schreibt eine Version, plus Zurücknehmen auf Entwurf |
| 22 | Nach dem Zurücknehmen | Unveränderter Entwurf: `unpublished_at` wird geleert, die bestehende Version läuft weiter. Abweichender Entwurf: neue Version |
| 23 | Abweichung erkennen | Bei jedem Aufruf rechnen — Key-Menge, Titel, `design`, `cover_key`. Keine `dirty`-Spalte |
| 24 | Galerie löschen | Hart, hinter Tippbestätigung des Titels. Export vorher, S3-Objekte per Job |
| 25 | Export beim Löschen | `s3://exports/galleries/{public_id}.json` plus `InternalNote` am Projekt. Ohne Projekt bleibt nur die Datei |
| 26 | Vorschau | Entwurfs-Pass nach dem Muster von `GalleryViewPass`, ~30 Minuten Laufzeit |
| 27 | Vorschau-Knopf und iframe | Beide. Kopf öffnet neuen Tab, iframe bleibt im Gestaltungs-Reiter |
| 28 | Upload und Wegnavigieren | `beforeunload` plus Inertia-Abbruch, solange das Tray läuft |
| 29 | Cover-Aufmachung „Ohne" | `cover_asset_id` bleibt gesetzt und trägt die Übersichtskarte |

### Oberfläche

| # | Frage | Entscheidung |
|---|---|---|
| 30 | Set-Liste bei einem Set | Verborgen, solange nur eins existiert |
| 31 | Set umbenennen | In der Zeile, Enter speichert, Escape verwirft |
| 32 | 850 Kacheln | `useVirtualList` aus `@vueuse/core` — steht schon in `package.json` |
| 33 | Bild groß ansehen | Doppelklick öffnet eine Leichtansicht über dem Raster, Pfeiltasten blättern |
| 34 | Statuswörter | Vier: `Entwurf` · `Veröffentlicht` · `Zurückgenommen` · `Abgelaufen`. Extern trägt keinen |
| 35 | Projektfilter | Eintrag „ohne Projekt" ganz oben |
| 36 | Original herunterladen | Eigene Route, antwortet mit kurzlebiger signierter S3-URL |

### Rechte und Prüfung

| # | Frage | Entscheidung |
|---|---|---|
| 37 | Berechtigungen | `publish`/`unpublish` → `ManagePortalAccess`, `delete` → `ManageDsgvo` (inhaberexklusiv), alles übrige über `manageMedia` |
| 38 | E2E-Baselines | `E2eSeeder` mitziehen, Baselines pro Etappe neu schießen |

## Datenmodell

### Neu: `gallery_sets`

`tenant_id`, `public_id`, `gallery_id` (FK, cascade), `name`, `position`,
`sort_key` (`taken_at` | `filename`), `sort_dir` (`asc` | `desc`),
Zeitstempel. Unique auf `(tenant_id, gallery_id, name)`, Index auf
`(tenant_id, gallery_id, position)`.

Die Sortierung sitzt am Set, nicht an der Galerie: ein Highlight-Set will
Aufnahmezeit, ein Fotobox-Set den Dateinamen des Automaten. Fehlt `taken_at`
— gescannt, exportiert, Screenshot —, greift `nulls last, filename asc`;
`created_at` wäre die Reihenfolge, in der die Queue fertig wurde, und die ist
bei parallelen Workern Zufall.

Jede Galerie entsteht mit einem Set „Highlights". Solange es das einzige
bleibt, blendet die Schiene die Liste aus.

### Neu: `gallery_grid_prefs`

`tenant_id`, `user_id`, `gallery_set_id`, `density`. Beide Fremdschlüssel
`cascadeOnDelete`. Dieselbe Form, die `recent_views` (C-03, #237) für
dieselbe Art Vorliebe schon verwendet — eine jsonb-Spalte am Nutzer würde
Waisen sammeln, die niemand aufräumt.

### `assets`

| Spalte | | |
|---|---|---|
| `gallery_set_id` | neu | FK auf `gallery_sets`, **not null** |
| `removed_from_draft_at` | neu | timestamptz, nullable |

**Kein `SoftDeletes`.** `BuildGalleryZip.php:179-184` löst die Keys einer
*veröffentlichten* Version über `Asset`-Zeilen auf; ein globaler Scope würde
diese Zeilen dort unsichtbar machen und das ZIP einer bereits ausgelieferten
Galerie brechen — durch genau die Maßnahme, die sie schützen soll.
Entwurfssichten filtern die Spalte ausdrücklich, versionsgebundene Leser
ignorieren sie.

Keine `position`-Spalte (Entscheidung 6).

### `galleries`

| Spalte | | |
|---|---|---|
| `cover_asset_id` | neu | nullable FK auf `assets`, `nullOnDelete` |
| `cover_focal_x`, `cover_focal_y` | neu | smallint 0–100, null = Mitte |
| `event_date` | neu | date, nullable |
| `unpublished_at` | neu | timestamptz, nullable |
| `assets` | **entfällt** | ersetzt durch `assets`-Zeilen, ohne Rückfüllung |

```sql
alter table galleries add constraint galleries_one_date
  check (shoot_id is null or event_date is null)
```

Angezeigtes Datum ist `shoot?->date ?? event_date`. Das Feld erscheint im
Formular erst, wenn kein Projekt und kein Shooting gewählt ist. Die Action,
die später ein Shooting anhängt, leert `event_date` im selben `update`; ein
Test hält das fest. Die Constraint bleibt als Netz und soll nie zuschlagen.

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

### `gallery_presets`

Neu: die vier `design`-Achsen und `is_default` (partielle Unique-Regel pro
Mandant). Keine Set-Struktur. `design` bleibt ganz-oder-gar-nicht: vier
Dreizustände sind lesbar, zwölf sind es nicht.

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

| Methode | Pfad | Recht |
|---|---|---|
| GET | `/galerien` | `viewAny` |
| GET/POST | `/galerien/neu`, `/galerien` | `create` |
| GET/POST | `/galerien/extern/neu`, `/galerien/extern` | `create` |
| GET/PATCH | `/galerien/{gallery}` | `manageMedia` |
| POST/PATCH/DELETE | `/galerien/{gallery}/sets[/{set}]` | `manageMedia` |
| PATCH | `/galerien/{gallery}/sets/reihenfolge` | `manageMedia` |
| POST | `/galerien/{gallery}/bilder/verschieben` | `manageMedia` |
| DELETE | `/galerien/{gallery}/bilder` | `manageMedia` |
| GET | `/galerien/{gallery}/bilder/{asset}/original` | `manageMedia` |
| PATCH | `/galerien/{gallery}/titelbild` | `manageMedia` |
| POST | `/galerien/{gallery}/uploads/pruefen` | `manageMedia` |
| POST | `/galerien/{gallery}/veroeffentlichen` | `publish` |
| DELETE | `/galerien/{gallery}/veroeffentlichung` | `publish` |
| DELETE | `/galerien/{gallery}` | `delete` |
| GET | `/galerien/{gallery}/vorschau` | `manageMedia` |

`/galerien/{gallery}/medien` entfällt.

`GalleryPolicy` bekommt `publish` (auf `Capability::ManagePortalAccess` —
laut eigenem Doc entscheidet die Fähigkeit bereits, „ob eines ihrer Projekte
zeigbereit ist") und `delete` (auf `Capability::ManageDsgvo`, inhaberexklusiv
wie `ManageStaff`). Ein zweiter Fotograf lädt hoch und veröffentlicht, löscht
aber nicht.

`/original` antwortet mit einer kurzlebigen signierten S3-URL, nie mit dem
Objekt selbst — ein PHP-Worker hängt sonst an einem 40-MB-RAW.

### Duplikate

`uploads/pruefen` bekommt `[{filename, size}]` und antwortet mit den Treffern
aus `assets` derselben Galerie (`filename` + `byte_size`, ohne
`removed_from_draft_at`). Der Dialog bietet *Überspringen* und *Ersetzen*.

Galerieweit, nicht setweit: `assets_gallery_checksum_unique` ist bereits
galerieweit. Bei setweiter Prüfung meldete die Vorabfrage „kein Duplikat", der
Ingest konvergierte danach trotzdem auf dieselbe Zeile, und das Bild landete
nicht im zweiten Set.

*Ersetzen* legt eine neue Zeile an, die Set-Zugehörigkeit und Titelbild-Rolle
der alten erbt; die alte bekommt `removed_from_draft_at`. Kein Überschreiben
eines vorhandenen Keys — derselbe Key mit anderen Bytes bricht den
Cache-Vertrag aus `GalleryImageController`.

### Veröffentlichen

Ein Knopf, dessen Beschriftung der Abweichungsrechnung folgt:

- Entwurf weicht ab → *Neu veröffentlichen*, schreibt eine Version, leert
  `unpublished_at`.
- Entwurf ist deckungsgleich und `unpublished_at` gesetzt → *Wieder
  freigeben*, leert nur `unpublished_at`.

Abweichung = Key-Menge des Entwurfs gegen `currentVersion->assets`, dazu
Titel, `design` und `cover_key`. Eine Abfrage plus json-Vergleich, bei jedem
Aufruf des Records neu. Keine `dirty`-Spalte, die eine künftige Action
vergessen kann.

### Löschen

Tippbestätigung des Titels. Der Job schreibt zuerst Favoriten und Kommentare
nach `s3://exports/galleries/{public_id}.json` und legt eine `InternalNote`
am Projekt an, die den Pfad nennt. `InternalNote` hängt an `project_id`; eine
Galerie ohne Projekt hat keinen Ort für die Notiz, für die bleibt nur die
Datei. Danach fallen die Zeilen, die S3-Objekte räumt derselbe Job weg, der
auch abgelaufene Galerien abräumt.

### Vorschau

Der iframe zeigt den **Entwurf**. `apps/gallery` ist ein eigener Prozess auf
eigenem Host und hält keine Admin-Sitzung, aber
`App\Gallery\Support\GalleryViewPass` gibt es schon: ein signierter Bearer,
den der Viewer als HttpOnly-Cookie hält (`apps/gallery/src/lib/viewPass.ts`).
Der Admin lässt sich einen Entwurfs-Pass mit kurzer Laufzeit ausstellen, die
iframe-URL trägt ihn, `GalleryReadController` liefert bei Entwurfs-Pass den
Entwurf statt der Version.

Eine zweite Umsetzung der Aufmachungen im Studio wäre die Alternative — sie
würde genau den Teil verdoppeln, der zwölf Layouts groß ist, und lügen,
sobald beide auseinanderlaufen.

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
| `admin/pages/Galleries/External.vue` | neu — schmaler Record ohne Schiene und Reiter |
| `admin/pages/Galleries/Record.vue` | Hülle: Kopf mit Statuschip, Vorschau, Veröffentlichen; linke Schiene |
| `admin/pages/Galleries/panes/Images.vue` | Auswahl, Sets, Dropzone, Leichtansicht |
| `admin/pages/Galleries/panes/Design.vue` | Titelbild, Fokus, Aufmachung, Farbe, Raster, Schrift, iframe |
| `admin/pages/Galleries/panes/Settings.vue` | Zugang, Angebote, Ablauf, Vorlage sichern, Löschen |
| `admin/pages/Galleries/panes/Activity.vue` | drei Blätter plus ZIP-Archive |
| `admin/components/gallery/GalleryRail.vue` | Titelbild, Icon-Reiter, Set-Liste |
| `admin/components/gallery/{DropZone,UploadTray,DuplicateDialog,AssetLightbox}.vue` | neu |
| `admin/pages/Galleries/Ingest.vue` | **gelöscht** |
| `admin/pages/Settings/Galleries.vue` | neu — Zeilenform wie der ⚙-Reiter |
| `admin/pages/Settings/GalleryPresets.vue` | neu — mit derselben iframe-Vorschau, plus `is_default` |

Formfelder kommen aus `resources/js/ui`. Kein rohes `<input>`.

### Übersicht

Status ist abgeleitet, keine Spalte: keine Version = `Entwurf` ·
`unpublished_at` gesetzt = `Zurückgenommen` · `expires_at` vorbei =
`Abgelaufen` · sonst `Veröffentlicht`. Externe Galerien tragen keinen. Kein
eigener Zustand für „läuft bald ab" — das ist eine Frist, und die steht
bereits auf der Karte, im Filter und in der Sortierung.

Sortierung: neueste zuerst nach `shoot?->date ?? event_date`, Galerien ohne
Datum nach `created_at` dahinter. Projektfilter mit „ohne Projekt" ganz oben —
`project_id` ist nullable und interne Galerien sind ausdrücklich vorgesehen.

### Bilder-Reiter

Klick wählt, Shift-Klick eine Strecke, ⌘A alles. Schwebende Auswahlleiste mit
*In Set verschieben* · *Herunterladen* · *Löschen*. Auswahl auf ein Set in der
Schiene ziehen verschiebt sie. Pro Bild ein `…`-Menü: Titelbild, In Set,
Original laden, Löschen. Doppelklick öffnet die Leichtansicht, Pfeiltasten
blättern — ohne sie ist *Löschen* ein Klick ins Blaue.

Das Raster rendert über `useVirtualList` aus `@vueuse/core`. Bei 850 Kacheln
ist das der Unterschied zwischen flüssiger und zäher Mehrfachauswahl, und die
Abhängigkeit steht bereits in `package.json`.

Läuft ein Upload, hält `beforeunload` plus ein Inertia-Abbruch den Nutzer auf
der Seite. Die Wiederaufnahme in `StartOrResumeMultipartUpload` ist für
abgerissene Leitungen da, nicht für einen vermeidbaren Klick.

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

„Ohne" heißt: die Auslieferung zeigt keinen Kopf. `cover_asset_id` bleibt
trotzdem gesetzt und trägt die Karte in der Admin-Übersicht — sonst kehrte
`assets[0]` zurück, das dieser Umbau gerade entfernt.

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
4. **Einstellungen** — ⚙-Reiter, Studio-Vorgaben, Vorlagen mit Vorschau,
   Löschen samt Export
5. **Aktivität** — drei Blätter plus die ZIP-Archive

Jede Etappe ist für sich lauffähig und einzeln zusammenführbar.

## Tests

Pest (`apps/studio/tests/Feature/Gallery`): Set anlegen, umbenennen, löschen
mit Verschieben und mit Mitlöschen · Mehrfachverschieben · Titelbild setzen
und Fokus zurücksetzen · Veröffentlichen schreibt `design` und `cover_key` in
die Version · Wieder freigeben schreibt keine Version, abweichender Entwurf
schon · Zurücknehmen schließt das Gate · Duplikatprüfung über Name und Größe ·
`removed_from_draft_at` verbirgt das Bild im Entwurf, nicht im ZIP einer
veröffentlichten Version · Shooting anhängen leert `event_date` · `delete`
verlangt `ManageDsgvo`, `publish` verlangt `ManagePortalAccess`.

Vitest für Auswahl- und Duplikat-Logik in `resources/js/admin/lib`.

Container mit `-e APP_SURFACE=admin`.

`E2eSeeder` schreibt künftig `assets`-Zeilen und Sets statt json. Die
Galerie-Baselines werden in derselben Etappe neu geschossen, in der der
jeweilige Screen fällt — der Seeder sät relativ zu `now()`, ein Stapel von
fünf Etappen alter Abweichungen ist nicht mehr lesbar.

## Nicht enthalten

- Rückfüllen von `width`, `height`, `taken_at`, `blurhash` für Altbestände
- Ein Bild in zwei Sets (bräuchte *Kopieren*, nicht doppeltes Hochladen)
- Freies Umsortieren per Ziehen im Raster
- Responsiver Umbau des Records unter 1200 px
- Studio-weite Gestaltungs-Vorgaben als dritte Übersteuerungsebene

## Entwürfe

Die Mockups dieser Sitzung liegen unter
`.superpowers/brainstorm/71703-1787477627/content/` (nicht versioniert):
`bestand.html`, `aufbau.html`, `uebersicht.html`, `anlegen.html`,
`gestaltung.html`, `cover-varianten.html`,
`einstellungen-veroeffentlichen.html`, `bilder.html`.
