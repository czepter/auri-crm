# Galerie-Verwaltung: Nav, Index, Anlegen, Vorlagen

Stand 2026-08-22. Auslöser: Galerien fehlen in der Admin-Nav.

## Ausgangslage

`AdminLayout.vue:41-43` nennt den Grund für das Fehlen: „Galerien is in the
draft's own sidebar and absent here for exactly that reason — the admin
gallery record is #254." Der Record-Screen existiert inzwischen
(`Galleries/Record.vue`, C-19 #255). Der Grund ist entfallen, der Eintrag nie
nachgezogen worden.

Dahinter liegen vier weitere Lücken:

- **Kein Index.** `routes/admin.php` kennt nur `/galerien/{gallery}` und
  `/galerien/{gallery}/medien`. Eine Galerie ist ausschließlich über die
  Projekt-Rail (`Projects/Show.vue:165`) oder den Kontakt
  (`Contacts/Edit.vue:312`) erreichbar.
- **Kein Anlegen.** `Gallery::create` steht nur in
  `Gallery/Actions/ImportLegacyGallery.php`. Im Admin entsteht keine Galerie.
- **Tote Schalter.** `PortalSettings::galleryDownloadsEnabled()`,
  `galleryListsEnabled()` und `galleryCommentsEnabled()` werden nur von
  `Crm/Http/Controllers/ContactController.php:146-148` aufgerufen, und zwar
  allein zum Rendern der Kontaktmaske. Kein Portal-Screen, keine API-Route und
  keine Middleware fragt sie ab. Downloads, Listen und Kommentare laufen
  unabhängig vom Schalterstand.
- **Zwei Ablaufquellen.** `Gallery::publish():131` liest
  `config('gallery.retention_days')`, `ImportLegacyGallery:133` liest
  `studio_settings.gallery_default_expiry_days`. Der Doc-Kommentar in
  `config/gallery.php` begründet, warum die Frist *kein* Studio-Setting sei —
  das Setting existiert seit `2026_08_16_000400` trotzdem, wird in
  `Settings/Galleries.vue:57` editiert und macht den Kommentar zur Legende.

## Entscheidungen

| # | Frage | Entscheidung |
|---|---|---|
| 1 | Ablaufquelle | Studio-Setting gewinnt, `config/gallery.php` wird gelöscht |
| 2 | Nav-Platzierung | Erste Gruppe, nach „Projekte", Label `Galerien` |
| 3 | Listeninhalt | Titel, Projekt, Bilder, Version, Ablauf; neueste zuerst; keine Filter |
| 4 | Sichtbarkeit | Sichtbare Projekte plus alle projektlosen Galerien |
| 5 | Schalter am Record | Tab `freigaben`, gewöhnliche Checkboxen, trotz dessen bisher rein lesender Natur |
| 6 | Externe Galerie | Eigener Formularzweig ohne Vorlage, Datum und Schalter |
| 7 | Vorlagen-Verwaltung | Eigene SettingsNav-Gruppe, eigener Screen, `ManageSettings` |
| 8 | Löschen | Vorlagen ja, Galerien nein |
| 9 | Benennung | Gruppe „Galerien", Einträge „Standardwerte" und „Vorlagen" |
| 10 | Vorlage anwenden | Clientseitig, das Formular füllt sich; der Server kennt keine Vorlage |
| 11 | Ablauf in der Vorlage | `expiry_mode` (`inherit`/`days`/`never`) plus `expiry_days` |
| 12 | Projektauswahl | Neues `ProjectTypeahead.vue` plus Such-Route |
| 13 | Ziel nach dem Anlegen | `/galerien/{id}/medien`, extern auf den Record |
| 15 | `MediaIngestTargetData` | `projectId`/`projectName` auf `?string` |
| 16 | Vorlagen-Screen | Liste mit Inline-Aufklappen, kein Screen je Vorlage |
| 17 | Nav-Zähler | Keiner |
| 18 | Tote Schalter | Scharfschalten, und zwar vor der Vorlage |
| 19 | Contact-Overrides | Bleiben als Geburtswert |
| 20 | Backfill | Entfällt, kein produktives System |
| 21 | Migrationsform | Direkt in die Create-Migration |
| 22 | Back-Merge | Nur die Galerie-Migrationen |
| 23 | Legacy-Hashes | Mitstreichen |

## Datenmodell

### `galleries`

Alles direkt in `2026_08_09_003100_create_galleries_table.php`; die sechs
additiven Migrationen werden aufgelöst und ihre `why`-Kommentare wandern mit.

Neu:

```php
$table->boolean('downloads_enabled')->default(true);
$table->boolean('lists_enabled')->default(true);
$table->boolean('comments_enabled')->default(true);
```

NOT NULL mit Default, keine Dreiwertigkeit: die Galerie entscheidet, und zwar
immer. Studio-Default, Kontakt und Vorlage bestimmen nur, womit eine Galerie
geboren wird — zur Laufzeit liest das Gate die Spalte und sonst nichts.

Geändert: `project_id` wird nullable. Eine Galerie ohne Projekt ist
studiointern und außerhalb des Portals — `GalleryAccessSession:64` bindet den
Kundenzugang an `visibleProjects()`, ohne Projekt greift das nie. Sie ist über
Link und Passwort erreichbar, in keiner Kundenansicht.

Aufzulösen in die Create-Migration:

- `2026_08_09_003300_add_external_link_to_galleries_table`
- `2026_08_09_003700_add_access_gates_to_galleries_table` — ohne
  `view_password_hash` und `download_pin_hash`
- `2026_08_15_000200_add_expires_at_to_galleries_table`
- `2026_08_18_000300_store_gallery_secrets_recoverably`
- `2026_08_18_000600_give_a_gallery_sets_and_a_look`
- der `view_count`-Teil aus `2026_08_16_000300_create_gallery_lists_tables`;
  die beiden Listen-Tabellen bleiben, wo sie sind

Die Legacy-Hashes entfallen ersatzlos. Damit verschwinden die beiden Casts
(`Gallery.php:76-77`), `Gallery::check()` wird zu `hash_equals` in
`checkViewPassword`/`checkDownloadPin`, und `requiresViewPassword()` sowie
`requiresDownloadPin()` prüfen je einen Wert statt zwei. Das Argument, das die
Spalten hielt — „dropping them would turn every gallery that already has a
gate into an open one at deploy time" — setzt Bestandsdaten voraus, die es
nicht gibt.

### `gallery_presets`

Neue Tabelle, neues Model `app/Gallery/Models/GalleryPreset.php` mit
`BelongsToTenant` und `HasPublicId`.

| Spalte | Typ | Bedeutung |
|---|---|---|
| `name` | string | „Hochzeit", „Portrait" |
| `design` | jsonb null | `cover`, `type`, `colour`, `grid` |
| `expiry_mode` | string | `inherit`, `days`, `never` |
| `expiry_days` | smallint null | nur bei `expiry_mode = days` |
| `requires_settled_balance` | bool null | null = erben |
| `downloads_enabled` | bool null | null = erben |
| `lists_enabled` | bool null | null = erben |
| `comments_enabled` | bool null | null = erben |

Die Booleans sind dreiwertig, weil eine Vorlage sonst zwangsläufig jede Ebene
darunter überschreibt. Eine Vorlage, die zu Downloads nichts sagt, lässt den
Kontaktwert stehen.

`expiry_mode` statt eines Sentinel-Integers: bei `expires_at` bedeutet null
schon „kein Ablauf", ein zweites null für „erben" wäre in einem Jahr nicht
mehr lesbar.

Kein `gallery_preset_id` auf `galleries`. Die Vorlage füllt beim Anlegen das
Formular und ist danach nicht mehr verbunden. Eine geänderte Vorlage erreicht
bestehende Galerien nicht — das ist die Eigenschaft, nicht der Mangel.

### Geburtskette

Beim Anlegen, je Wert, erster nicht leerer gewinnt:

```
Formulareingabe → Vorlage → Contact des Projekts → Studio-Default
```

Der Contact-Schritt entfällt bei projektloser Galerie. Die Kette läuft
clientseitig im Formular ab; der Store empfängt fertige Werte.

## Berechtigung

`GalleryPolicy` bekommt drei Methoden:

```php
public function viewAny(User $user): bool     // ManageShoots
public function create(User $user): bool      // ManageShoots
```

und `manageMedia` einen Null-Zweig: ohne Projekt genügt
`Capability::ManageShoots`, mit Projekt bleibt es bei
`ProjectPolicy::manageShoots`.

Folge, ausdrücklich: eine Rolle ohne `ViewAllProjects` sieht im Index ihre
eigenen Projekte plus **jede** projektlose Galerie des Studios. Es gibt nichts,
woran ein Filter greifen könnte. Wer das nicht will, nimmt „Projekt optional"
zurück, nicht den Filter hinzu.

Nicht null-fest und anzupassen:

- `Policies/GalleryPolicy.php:25`
- `Media/Data/MediaIngestTargetData.php:20-21,33-34` — beide Felder auf
  `?string`, `fromGallery` mit `$gallery->project?->…`. Die „Ziel"-Karte in
  `Galleries/Ingest.vue` zeigt die Zeile nur, wenn ein Projekt da ist.
- `Gallery/Actions/ReadAfterDeliveryColumn.php:86-87`

Bereits null-fest: `GalleryAccessSession:64`, `GalleryVisitor:49`,
`GalleryRecordController:48,71`, `MediaIngestController:27`.

## Gates scharfschalten

Zuerst zu bauen, sonst setzt die Vorlage Werte, die nichts bewirken.

Jedes Gate liest die Spalte auf der Galerie.

**Downloads** — `routes/portal.php:254-270` (`/download`, `/download/pin`,
`/download/vorbereiten`, `/download/email`, `/download/email/{token}`) und der
Knopf in `Portal/pages/Gallery/Show.vue:309`. Gesperrt heißt: keine Route,
kein Knopf.

**Listen** — `routes/portal.php:140-145` (`storeList`, `renameList`,
`destroyList`, `toggleItem`) sowie die Listen-UI in `Gallery/Show.vue` und
`GalleryLightbox.vue`.

**Kommentare und Favoriten** — `routes/portal.php:244-247`
(`toggleFavorite`, `commentStore`) **und** die API-Fläche für den
Astro-Viewer, `routes/api.php:60-74`. Zwei Oberflächen, ein Wert.

Die Sperre gehört in eine Middleware neben `gallery.gate` und `gallery.pass`,
nicht in fünf Controller.

## Routen

```php
// admin.php, /galerien/neu über /galerien/{gallery} — dieselbe Regel wie /pakete/neu
// GalleryIndexController, nicht GalleryController: den Namen tragen bereits
// App\Portal\Http\Controllers\GalleryController und, für dieselbe Entität,
// GalleryRecordController.
Route::get('/galerien', [GalleryIndexController::class, 'index'])->name('galleries.index');
Route::get('/galerien/neu', [GalleryIndexController::class, 'create'])->name('galleries.create');
Route::post('/galerien', [GalleryIndexController::class, 'store'])->name('galleries.store');

Route::get('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'index'])
    ->name('gallery-presets.index');
Route::post('/einstellungen/galerie-vorlagen', [GalleryPresetController::class, 'store'])
    ->name('gallery-presets.store');
Route::patch('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'update'])
    ->name('gallery-presets.update');
Route::delete('/einstellungen/galerie-vorlagen/{galleryPreset}', [GalleryPresetController::class, 'destroy'])
    ->name('gallery-presets.destroy');
```

Plus eine Suchroute für `ProjectTypeahead`, nach dem Muster, das
`ContactTypeahead` bedient.

Gelöscht: `config/gallery.php`. `Gallery::publish()` liest stattdessen
`StudioSettingsResolver::galleryDefaults()['expiryDays']` und stempelt weiter
nur, wenn `expires_at` noch leer ist.

## Screens

### Nav

`AdminLayout.vue`, erste Gruppe, nach „Projekte":

```js
{ key: 'galerien', label: 'Galerien', href: '/galerien', needs: 'manage_shoots',
  icon: '…' },
```

Icon in der Sprache der Nachbarn: 17px, stroke 1.6, runde Enden — zwei
überlappende Rechtecke. Kein Zähler.

### `Galleries/Index.vue`

Eine Zeile je Galerie: Titel, Projekt, Bildanzahl, veröffentlichte Version,
Ablauf. Neueste zuerst. Kopf trägt den Knopf „Galerie anlegen", nach dem
Muster von `Packages/Index.vue:29`.

Leerzustand ist der Knopf, kein Satz darüber.

### `Galleries/Create.vue`

Zwei Zweige, gewählt über eine `ChoiceGroup`.

*Intern:* Projekt (`ProjectTypeahead`, optional), Titel, Vorlage, Ablaufdatum
(`<input type="date">`), Zahlungssperre, drei Checkboxen. Die Vorlagenauswahl
füllt die Felder darunter; jedes bleibt änderbar.

*Extern:* Projekt, Titel, URL. Sonst nichts — eine externe Galerie hostet
nichts, `publish()` wirft bei ihr `LogicException`, und Ablauf, Vorlage und
Schalter greifen ins Leere.

Nach dem Speichern: intern auf `/galerien/{id}/medien`, extern auf den Record.

### `Galleries/Record.vue`

Tab `freigaben` bekommt die drei Checkboxen. Der Tab ist heute rein lesend und
wiederholt, was im Kopf (`Record.vue:123,128`) editierbar steht — diese
bestehende Dopplung wird hier nicht angefasst.

### `Settings/GalleryPresets.vue`

Liste der Vorlagen, jede per Klick aufklappbar. Sieben Felder, kein eigener
Screen je Vorlage. Anlegen und Löschen an Ort und Stelle.

### `SettingsNav.vue`

Neue Gruppe nach `groupPortal`; `galleries` verlässt die Portal-Gruppe.

```js
{ group: 'settings.groupGalleries' },
{ key: 'galleries',      href: '/einstellungen/galerien',         label: 'settings.navGalleryDefaults' },
{ key: 'galleryPresets', href: '/einstellungen/galerie-vorlagen', label: 'settings.navGalleryPresets' },
```

## Copy

Neue Schlüssel in `resources/js/locales/de.json`:

```
settings.groupGalleries      Galerien
settings.navGalleryDefaults  Standardwerte
settings.navGalleryPresets   Vorlagen
galleries.title              Galerien
galleries.create             Galerie anlegen
galleries.mode.internal      Eigene Galerie
galleries.mode.external      Externer Link
galleryPresets.title         Vorlagen
galleryPresets.create        Vorlage anlegen
galleryPresets.inherit       Studio-Wert
```

`settings.navGalleries` entfällt. Bestehende Beschriftungen aus
`gallerySettings.*` werden für die Schalter wiederverwendet, nicht
verdoppelt.

## Tests

Pest, `apps/studio/tests`, mit `-e APP_SURFACE=admin`.

- Gate: Galerie mit `downloads_enabled = false` — `/download` antwortet 403,
  der Knopf fehlt in den Props. Je einer für Listen und Kommentare, letzterer
  auch gegen `routes/api.php`.
- Anlegen: Store mit Projekt legt an und leitet auf `/medien`; ohne Projekt
  ebenso; extern leitet auf den Record.
- Policy: Rolle ohne `ViewAllProjects` sieht im Index fremde Projektgalerien
  nicht, projektlose schon.
- Vorlage: die Geburtskette, als Vitest gegen die Formularlogik — sie läuft
  clientseitig, also gehört der Test dorthin.
- `publish()` stempelt `expires_at` aus dem Studio-Setting, nicht mehr aus der
  Config.

## Nicht im Umfang

- **Galerie löschen.** Bricht Links, die beim Kunden liegen, und hängt an
  `gallery_versions`, `asset_favorites`, `asset_comments` und `zip_builds` per
  `cascadeOnDelete`. Eigenes Vorhaben, mit eigener Entscheidung über Widerruf
  statt Löschung.
- **Back-Merge der übrigen Migrationen.** Nur Galerie. Repoweit ist ein
  eigener Commit.
- **Die Dopplung im Tab `freigaben`.** Bleibt.
- **Filter und Suche im Index.** Wenn die Liste dafür lang genug wird.

## Reihenfolge

1. Gates scharfschalten — die drei Spalten, die Middleware, die UI
2. Ablaufquelle vereinheitlichen, `config/gallery.php` löschen
3. Back-Merge der Galerie-Migrationen, Legacy-Hashes streichen
4. `project_id` nullable, Policy, die drei nicht null-festen Stellen
5. Index und Nav-Eintrag
6. Vorlagen: Tabelle, Model, Settings-Screen, SettingsNav-Gruppe
7. Anlegen-Formular mit `ProjectTypeahead` und Geburtskette

Schritt 1 bis 5 sind je für sich lieferbar. Schritt 7 setzt 2 und 6 voraus.
