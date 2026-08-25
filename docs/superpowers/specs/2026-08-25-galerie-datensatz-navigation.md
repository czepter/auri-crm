# Galerie-Datensatz: Navigation, Routen, Titelbild

Stand 2026-08-25. Ändert Entscheidung 1 und 9 aus
[2026-08-23-galerie-verwaltung-redesign-design.md](2026-08-23-galerie-verwaltung-redesign-design.md).
Entwurf: `mockups/redesign/crm-galerie-record.html` (veröffentlicht),
`crm-galerie-record-entwurf.html` (vor der ersten Fassung).

## Anlass

Entscheidung 1 hat die schmale Schiene mit vier Glyphen `▦ ✎ ⚙ ◷` aus den
Pixieset-Screenshots übernommen. Im Browser gefahren sah sie aus wie eine
schlechte Kopie davon, und sie widerspricht dem Haus: das Projekt trägt seit
`ProjectShell.vue` eine beschriftete Spalte, und der Kommentar dort lehnt einen
Streifen über dem Inhalt ausdrücklich ab, weil Einträge mit einem Zähler
daneben eine linke Kante zum Ausrichten brauchen. Reiter kommen nicht in
Frage — die Anwendung hat nirgends welche.

## Was bereits gebaut ist

`GalleryRail.vue` kann das meiste schon. Wer aus dieser Spec baut, sucht sonst
Änderungen, die es nicht gibt:

| | |
|---|---|
| Titelbild 4:3 oben in der Spalte | `GalleryRail.vue:150` |
| Sets nur solange `tab === 'bilder'` | `GalleryRail.vue:169` |
| `Alle` erst ab zwei Sets | `GalleryRail.vue:177` |
| Ziehen zum Umsortieren, Doppelklick zum Umbenennen | `GalleryRail.vue:136` |
| Einstellungs-Unterspalte mit An/Aus-Pillen | `GalleryRail.vue:54` |
| Set löschen mit Ziel-Set oder Bildern | `GalleryRail.vue:125` |

Offen ist alles, was unten eine Nummer hat.

## Entscheidungen

### Spalte

| # | Frage | Entscheidung |
|---|---|---|
| 39 | Glyphen | Werden Wörter mit Zähler: `Bilder 220 · Gestaltung · Einstellungen · Aktivität 41`. Der Zähler ist der halbe Nutzen, und das Projekt macht es so |
| 40 | Breite | 212px bleibt. Das Projekt bleibt bei 150px — `Getting Ready 38` passt bei 150px nicht in eine Zeile |
| 41 | Gemeinsames Bauteil | `RecordNav.vue` in `admin/components/`, Props `width` und `tabs`. Slot `#above` für das Titelbild; das Projekt füllt ihn nicht |
| 42 | Sets im Datenvertrag | `tabs[].children` mit `key`, `label`, `count`, `href`, `reorderable` — kein `#under-bilder`-Slot, der den Sonderfall im Namen trüge. Shootings könnten später dasselbe tun |
| 43 | Wer zieht | `RecordNav` zeichnet Griff und `draggable` und wirft `reorder` mit der neuen Reihenfolge. Umbenennen und Löschen bleiben Galeriesache und hängen an `#child-actions` — ein Lösch-Dialog mit Ziel-Set gehört nicht in ein Bauteil, das Projekte benutzen |
| 44 | `ProjectShell` | Zieht in derselben Etappe um, `ProjectHeaderData:89` bekommt `children => []`. Ein geteiltes Bauteil mit einem Benutzer ist die Abstraktion, vor der `CLAUDE.md` warnt |

### Routen

| # | Frage | Entscheidung |
|---|---|---|
| 45 | Bereich in der URL | Eigenes Segment: `/galerien/{gallery}/{bereich}`. Das Projekt macht das längst — `/auftraege/{project}/shootings`, `/nachrichten`, `/angebote`. Die Galerie ist der Ausreißer: Bereich und Set sind `ref()` in `Record.vue`, Neuladen wirft dich auf *Bilder / Alle*, die Zurück-Taste verlässt die Galerie |
| 46 | Set in der URL | Auch Segment: `/galerien/{gallery}/bilder/{set}` |
| 47 | Einstellungsabschnitt | Auch Segment: `/galerien/{gallery}/einstellungen/{abschnitt}` |
| 48 | `Alle` | Hat keine `public_id` und bekommt kein eigenes Wort. `/galerien/{gallery}/bilder` **ist** `Alle`; bei genau einem Set zeigt dieselbe URL dieses Set, ohne Umleitung — dieselbe Regel, die die Navigation ohnehin fährt |
| 49 | Parametername | `{gallerySet}` wird `{set}`. Laravels implizites Scoping rät `Gallery::gallerySets()`, und die Relation heißt `sets()`. Trifft sechs Routen, `GallerySetController` und jeden `route()`-Aufruf |
| 50 | Scoping | `scopeBindings()` auf die ganze Gruppe, nicht nur auf Sets. `{asset}` in `/bilder/{asset}/original` und `/vorschau` bindet heute mandantenweit (`GalleryAssetController.php:94` schreibt es hin). Dafür braucht `Gallery` eine echte `assets(): HasMany`; `draftAssets()` wird ein Filter darauf statt eines eigenen Aufbaus über `sets()->with()` |
| 51 | Unbekanntes Segment | Umleitung auf den Einstieg des Bereichs: unbekannter Abschnitt auf `allgemein`, gelöschtes Set auf `/bilder`. Ein Set zusammenzulegen ist Aufräumen, kein Grund, alte Lesezeichen zu vergiften |
| 52 | Controllerform | Ein Controller, eine Methode, Bereich als Argument. Die teuren Teile werden `Inertia::optional()`, der Bereichswechsel damit ein Partial Reload. Vier Methoden mit vier Inertia-Seiten würden eine Hülle vervierfachen, die es schon gibt |
| 53 | Was immer lädt | Galerie, Titelbild, Sets mit Zählern, beide Navigationszähler. Auf Abruf: `assets`, `activity`, `builds`, `lists`, `versions`, `presets`, `failedAssets`, `pendingUploads` |

### Zähler

| # | Frage | Entscheidung |
|---|---|---|
| 54 | Was `Bilder` zählt | Die veröffentlichte Fassung, nicht den Entwurf. Die Zahl steht neben einer Navigation und beantwortet „wie viel ist beim Kunden" |
| 55 | Ohne Fassung | Kein Zähler. Die ehrliche Antwort vor der ersten Veröffentlichung ist keine Null, sondern gar nichts — die Sets tragen ihre eigenen |
| 56 | Zurückgenommen, abgelaufen | Fassungszahl wie bei veröffentlicht. Dass sie niemand erreicht, sagt der Kopf zwei Zentimeter weiter oben |
| 57 | Abweichender Entwurf | `220 → 230`, sobald mehr im Entwurf liegt als in der Fassung. Ohne das ist ein Upload, der nicht veröffentlicht wurde, in der Navigation unsichtbar — genau der Zustand, der diese Sitzung eine Stunde gekostet hat |
| 58 | Was `Aktivität` zählt | Markierungen und Kommentare, kumulativ. Aufrufe nicht: ein Zähler taugt nur für eine Menge, die man abarbeiten kann. Der Rücksetzer ist eingebaut — `AssetFavorite` hängt an `gallery_version_id`, jede neue Fassung fängt von vorn an |

### Titelbild

| # | Frage | Entscheidung |
|---|---|---|
| 59 | Erstes Bild | Das erste Bild wird Titelbild, danach änderbar. „Erstes" heißt: alphabetisch erstes im ersten Set — nicht das erste, das den Ingest schafft, denn Multipart-Uploads werden in beliebiger Reihenfolge fertig |
| 60 | Wann gesetzt | Wenn `cover_asset_id` null ist **und** keine Upload-Sitzung dieser Galerie mehr offen ist. Kein `batch_id`, kein verzögerter Job: `multipart_uploads` kennt keinen Stapel, und „keine offene Sitzung mehr" ist derselbe Zeitpunkt |
| 61 | Gelöschtes Titelbild | Rückt nach, nach derselben Regel. Eine Galerie ohne Titelbild ist kein Zustand, den jemand absichtlich herstellt |
| 62 | Wo die Regel steht | Eine Action `EnsureGalleryCover`, gerufen aus `IngestCompletedUpload`, `GalleryAssetController::remove` und `GallerySetController::destroy`. Dazu `on delete set null` als Netz. Ein Auslöser mehr darf keine zweite Kopie der Regel bedeuten |
| 63 | Leerer Zustand | Ein gestrichelter Platz in der Form, die das Bild bekommt, mit eigener Aktion *Bild wählen*. Kein Satz darüber, dass keins gesetzt ist, und kein Wort im Statuschip — sonst steht dasselbe zweimal da |
| 64 | Klick | Führt nach *Gestaltung*. Ein Bild, das aussieht wie ein Knopf, muss einer sein |

### Schmale Schirme

| # | Frage | Entscheidung |
|---|---|---|
| 65 | Umfang | Record, Übersicht und beide Einstellungsseiten (`Settings/Galleries.vue`, `GalleryPresets.vue`). Damit fällt „kein responsiver Umbau" aus Entscheidung 9 für den ganzen Galeriebereich weg |
| 66 | Bruchpunkte | 900px für die Spalten: Navigation legt sich hin und scrollt seitwärts, Sets in eine zweite Zeile, Einstellungen einspaltig. 640px für Raster und Tabellen: Raster vierspaltig, Leichtansicht Vollbild, Kopfaktionen zu einem `…`-Menü, Aktivitätstabellen werden Listen (`thead { display: none }` steht schon in `crm.css`) |
| 67 | Schnitt | Eine Etappe, ein Baseline-Lauf am Schluss. Der Umbau fasst dieselben Dateien an wie die Navigation; zwei Etappen hieße, 54 Screenshots zweimal zu prüfen. Der Lauf gehört an denselben Tag wie die Prüfung — `E2eSeeder` sät relativ zu `now()` |

## Betroffene Stellen

| Datei | |
|---|---|
| `admin/components/RecordNav.vue` | neu — Breite, `tabs`, `children`, `#above`, `#child-actions`, `reorder` |
| `admin/components/gallery/GalleryRail.vue` | wird `GalleryNav.vue`: füllt `RecordNav`, behält Umbenennen und Löschen |
| `admin/components/ProjectShell.vue` | benutzt `RecordNav` mit `width=150` |
| `Crm/Data/ProjectHeaderData.php:88` | `children => []` je Reiter |
| `Gallery/Data/GalleryNavData.php` | neu — Titelbild, vier Einträge, Sets als `children`, beide Zähler |
| `Gallery/Http/Controllers/GalleryRecordController.php` | Bereich als Argument, teure Props `optional()` |
| `Gallery/Models/Gallery.php` | `assets(): HasMany`, `draftAssets()` als Filter darauf |
| `Gallery/Actions/EnsureGalleryCover.php` | neu |
| `routes/admin.php` | Segmente, `{set}`, `scopeBindings()`, Umleitungen |
| `admin/pages/Galleries/Record.vue` | `tab`/`activeSetId`-Refs entfallen, kommen aus der Route |

## Tests

- Scoping: die `public_id` eines fremden Sets und eines fremden Bildes unter
  der eigenen Galerie-URL antworten 404. Ohne diesen Test fällt `scopeBindings`
  wieder ab, ohne dass es auffällt.
- Umleitung: gelöschtes Set → `/bilder`, unbekannter Abschnitt → `allgemein`.
- Zähler: Entwurf ohne Zähler; nach der ersten Fassung die Fassungszahl;
  zehn Bilder mehr im Entwurf ergeben `220 → 230`; zurückgenommen behält die
  Zahl.
- Titelbild: nach dem letzten Ingest steht das alphabetisch erste Bild;
  ein zweiter Stapel überschreibt es nicht; Titelbild löschen rückt nach;
  das Set mit dem Titelbild löschen rückt nach.
- SSR `RecordNav`: ein Set ohne `Alle`, ab zwei mit; ohne `cover` der
  gestrichelte Platz, und zwar in jedem Bereich.

## Nicht enthalten

- Gemerkter Auf-/Zuklapp-Zustand der Sets. Wer bei den Einstellungen
  arbeitet, sucht keine Sets
- Sets als Baum unter 640px — der Einzug ist dort kein Signal mehr
- Ungelesen-Zähler an *Aktivität* (bräuchte `gallery_seen_at` je Nutzer)
- Reihenfolge der Dateiauswahl durch `multipart_uploads` tragen
