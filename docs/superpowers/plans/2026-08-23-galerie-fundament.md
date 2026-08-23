# Galerie-Fundament (Etappe 1) — Umsetzungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eine im Studio angelegte Galerie kennt ihre hochgeladenen Bilder, ordnet sie in Sets, erkennt Duplikate vor dem Upload und lässt sich veröffentlichen und zurücknehmen.

**Architecture:** Die `assets`-Tabelle wird die einzige Wahrheit über die Bilder einer Galerie; `galleries.assets` (json) fällt weg. Sets werden eine eigene Tabelle mit Fremdschlüssel an `assets`. `Gallery::publish()` schreibt den Schnappschuss künftig aus den Zeilen und nimmt Gestaltung und Titelbild mit, damit ein veröffentlichter Stand sich nicht mehr rückwirkend ändert.

**Tech Stack:** Laravel 12 (Pest), Inertia + Vue 3 + TypeScript, Tailwind, PostgreSQL, S3-kompatible Ablage, `@vueuse/core`.

**Spec:** `docs/superpowers/specs/2026-08-23-galerie-verwaltung-redesign-design.md`

## Global Constraints

- **Testkommando Pest:** `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='<name>'`. Admin-Routen sind unter jeder anderen Surface nicht registriert und antworten 404.
- **Testkommando Vitest:** `pnpm --filter studio test`. **Lint:** `pnpm lint`.
- **Migrationen sind neue Dateien.** Nie in eine bereits gelaufene Datei falten — das lässt bestehende Datenbanken auf altem Stand stehen.
- **Kein `SoftDeletes` an `Asset`.** `BuildGalleryZip.php:179-184` löst die Keys veröffentlichter Versionen über `Asset`-Zeilen auf; ein globaler Scope bräche das ZIP ausgelieferter Galerien. Entwurfssichten filtern `removed_from_draft_at` ausdrücklich.
- **Formfelder aus `resources/js/ui`.** Kein rohes `<input>`, kein rohes `<select>`.
- **Deutsche Beschriftungen sind knapp und nativ.** Ein Substantiv schlägt einen Satz. Keine Emoji, keine Ausrufezeichen, keine erklärenden Hinweiszeilen. Vor dem Übernehmen eines Strings die drei Nachbarn in `de.json` lesen.
- **Kommentare nur für das Warum.** Kein Kommentar, der die Signatur wiederholt.
- **Rechte:** Sets, Bilder, Upload, Titelbild → `manageMedia`. Veröffentlichen und Zurücknehmen → neue Policy-Methode `publish` auf `Capability::ManagePortalAccess`.
- Nach jedem Task committen. Conventional Commits, Betreff ≤ 50 Zeichen.

---

## Dateiübersicht

**Neu (PHP)**

| Datei | Verantwortung |
|---|---|
| `app/Gallery/Models/GallerySet.php` | Ein benanntes Fach innerhalb einer Galerie samt Sortierregel |
| `app/Gallery/Http/Controllers/GallerySetController.php` | Anlegen, Umbenennen, Sortierregel, Reihenfolge, Löschen |
| `app/Gallery/Http/Controllers/GalleryAssetController.php` | Mehrfach verschieben, aus dem Entwurf nehmen, Titelbild |
| `app/Gallery/Http/Controllers/GalleryPublicationController.php` | Veröffentlichen und Zurücknehmen |
| `app/Gallery/Actions/DeleteGallerySet.php` | Set löschen mit Ziel oder mit Inhalt |
| `app/Gallery/Actions/PublishGallery.php` | Abweichung prüfen, Version schreiben oder nur freigeben |
| `app/Gallery/Support/DraftDeviation.php` | Vergleich Entwurf gegen letzte Version |
| `app/Media/Http/Controllers/UploadPrecheckController.php` | Duplikate vor dem Upload |
| `database/factories/Gallery/GallerySetFactory.php` | |

**Neu (Vue)**

| Datei | Verantwortung |
|---|---|
| `resources/js/admin/components/gallery/GalleryRail.vue` | Titelbild, vier Reiter, Set-Liste |
| `resources/js/admin/components/gallery/DropZone.vue` | Ablagefläche eines Sets |
| `resources/js/admin/components/gallery/UploadTray.vue` | Schwebender Fortschritt |
| `resources/js/admin/components/gallery/DuplicateDialog.vue` | Überspringen oder Ersetzen |
| `resources/js/admin/components/gallery/AssetLightbox.vue` | Leichtansicht mit Pfeiltasten |
| `resources/js/admin/pages/Galleries/panes/Images.vue` | Raster, Auswahl, Dichte |
| `resources/js/admin/lib/gallerySelection.ts` | Auswahllogik, ohne DOM |

**Geändert**

`app/Gallery/Models/Gallery.php` · `app/Gallery/Models/GalleryVersion.php` · `app/Media/Models/Asset.php` · `app/Media/Models/MultipartUpload.php` · `app/Media/Actions/IngestCompletedUpload.php` · `app/Media/Http/Controllers/MultipartUploadController.php` · `app/Gallery/Http/Controllers/GalleryRecordController.php` · `app/Gallery/Data/GalleryRowData.php` · `app/Gallery/Actions/ImportLegacyGallery.php` · `app/Policies/GalleryPolicy.php` · `app/Http/Middleware/EnsureGalleryGate.php` · `database/seeders/E2eSeeder.php` · `database/factories/Gallery/GalleryFactory.php` · `routes/admin.php` · `resources/js/admin/pages/Galleries/Record.vue` · `resources/js/locales/de.json`

**Gelöscht**

`resources/js/admin/pages/Galleries/Ingest.vue` · `app/Media/Http/Controllers/MediaIngestController.php` · `app/Media/Data/MediaIngestTargetData.php`

---

## Task 1: Tabelle und Modell für Sets

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000100_create_gallery_sets_table.php`
- Create: `apps/studio/app/Gallery/Models/GallerySet.php`
- Create: `apps/studio/database/factories/Gallery/GallerySetFactory.php`
- Test: `apps/studio/tests/Feature/Gallery/GallerySetTest.php`

**Interfaces:**
- Produces: `App\Gallery\Models\GallerySet` mit `gallery(): BelongsTo`, `assets(): HasMany`, `$fillable = ['gallery_id', 'name', 'position', 'sort_key', 'sort_dir']`. Enum-freie Stringwerte: `sort_key` ist `taken_at` oder `filename`, `sort_dir` ist `asc` oder `desc`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GallerySetTest.php`

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('holds a named set with its own sort rule', function () {
    $gallery = Gallery::factory()->create();

    $set = GallerySet::create([
        'gallery_id' => $gallery->id,
        'name' => 'Trauung',
        'position' => 0,
    ]);

    expect($set->sort_key)->toBe('taken_at')
        ->and($set->sort_dir)->toBe('asc')
        ->and($set->gallery->is($gallery))->toBeTrue();
});

it('refuses two sets of the same name in one gallery', function () {
    $gallery = Gallery::factory()->create();

    GallerySet::create(['gallery_id' => $gallery->id, 'name' => 'Feier', 'position' => 0]);

    expect(fn () => GallerySet::create(['gallery_id' => $gallery->id, 'name' => 'Feier', 'position' => 1]))
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='holds a named set'`
Expected: FAIL mit `Class "App\Gallery\Models\GallerySet" not found`

- [ ] **Step 3: Migration schreiben**

`apps/studio/database/migrations/2026_08_24_000100_create_gallery_sets_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sets waren bis hier ein Schlüssel im Asset-Eintrag der json-Spalte,
     * den nichts schrieb. Als Zeile tragen sie das, was ein Fach braucht:
     * eine Reihenfolge in der Schiene und eine Sortierregel für ihren
     * Inhalt. Die Regel sitzt am Set, nicht an der Galerie — ein
     * Highlight-Set will die Aufnahmezeit, ein Fotobox-Set den Dateinamen
     * des Automaten.
     */
    public function up(): void
    {
        Schema::create('gallery_sets', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('position')->default(0);

            $table->string('sort_key')->default('taken_at');
            $table->string('sort_dir')->default('asc');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'gallery_id', 'name']);
            $table->index(['tenant_id', 'gallery_id', 'position']);
        });

        // Jede bestehende Galerie bekommt ihr Fach, bevor der Fremdschlüssel
        // an assets not null wird (Task 2).
        DB::statement(<<<'SQL'
            insert into gallery_sets (tenant_id, public_id, gallery_id, name, position, created_at, updated_at)
            select tenant_id, md5(random()::text || id::text), id, 'Highlights', 0, now(), now()
            from galleries
            where is_external = false
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_sets');
    }
};
```

- [ ] **Step 4: Modell und Factory schreiben**

`apps/studio/app/Gallery/Models/GallerySet.php`

```php
<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Media\Models\Asset;
use Database\Factories\Gallery\GallerySetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein benanntes Fach innerhalb einer Galerie. Die Sortierregel gehört
 * hierher und nicht an die Galerie, weil zwei Fächer derselben Galerie
 * verschiedene Regeln wollen.
 */
class GallerySet extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'name', 'position', 'sort_key', 'sort_dir'];

    protected $attributes = [
        'position' => 0,
        'sort_key' => 'taken_at',
        'sort_dir' => 'asc',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    protected static function newFactory(): Factory
    {
        return GallerySetFactory::new();
    }
}
```

`apps/studio/database/factories/Gallery/GallerySetFactory.php`

```php
<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GallerySet>
 */
class GallerySetFactory extends Factory
{
    protected $model = GallerySet::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'name' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
```

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GallerySetTest.php`
Expected: PASS, 2 Tests

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000100_create_gallery_sets_table.php apps/studio/app/Gallery/Models/GallerySet.php apps/studio/database/factories/Gallery/GallerySetFactory.php apps/studio/tests/Feature/Gallery/GallerySetTest.php
git commit -m "feat(gallery): Sets als eigene Tabelle"
```

---

## Task 2: `assets` bekommt Set und Entwurfsstatus

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000200_add_set_and_draft_state_to_assets_table.php`
- Modify: `apps/studio/app/Media/Models/Asset.php`
- Modify: `apps/studio/database/factories/Media/AssetFactory.php`
- Test: `apps/studio/tests/Feature/Gallery/GallerySetTest.php` (erweitert)

**Interfaces:**
- Consumes: `GallerySet` aus Task 1.
- Produces: `Asset::$fillable` um `gallery_set_id` und `removed_from_draft_at` erweitert; `Asset::set(): BelongsTo`; Scope `Asset::inDraft()` filtert `removed_from_draft_at is null`.

- [ ] **Step 1: Failing test schreiben**

Anhängen an `apps/studio/tests/Feature/Gallery/GallerySetTest.php`

```php
it('keeps a removed asset out of the draft and inside the version', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $stays = Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id]);
    $goes = Asset::factory()->create([
        'gallery_id' => $gallery->id,
        'gallery_set_id' => $set->id,
        'removed_from_draft_at' => now(),
    ]);

    expect(Asset::inDraft()->pluck('id')->all())->toBe([$stays->id])
        // Ohne globalen Scope bleibt die Zeile für BuildGalleryZip auffindbar,
        // sonst braeche das ZIP einer bereits ausgelieferten Version.
        ->and(Asset::whereKey($goes->id)->exists())->toBeTrue();
});
```

Importe oben ergänzen: `use App\Media\Models\Asset;`

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='keeps a removed asset'`
Expected: FAIL, `Column not found: gallery_set_id`

- [ ] **Step 3: Migration schreiben**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * removed_from_draft_at statt SoftDeletes: BuildGalleryZip löst die Keys
     * einer veröffentlichten Version über Asset-Zeilen auf, ein globaler
     * Scope machte sie dort unsichtbar und bräche das ZIP einer bereits
     * ausgelieferten Galerie. Der Name sagt, was passiert ist — das Bild hat
     * den Entwurf verlassen, es ist nicht gestorben.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->after('gallery_id')
                ->constrained('gallery_sets')->cascadeOnDelete();
            $table->timestampTz('removed_from_draft_at')->nullable()->after('error');
        });

        DB::statement(<<<'SQL'
            update assets a
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = a.gallery_id
               and s.position = 0
        SQL);

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_set_id');
            $table->dropColumn('removed_from_draft_at');
        });
    }
};
```

- [ ] **Step 4: Modell erweitern**

In `apps/studio/app/Media/Models/Asset.php`: `'gallery_set_id'` und `'removed_from_draft_at'` in `$fillable` aufnehmen, `'removed_from_draft_at' => 'datetime'` in `casts()`, und ergänzen:

```php
    public function set(): BelongsTo
    {
        return $this->belongsTo(GallerySet::class, 'gallery_set_id');
    }

    /** Was der Entwurf zeigt. Versionsgebundene Leser (ZIP, Bildauslieferung) fragen das nie. */
    public function scopeInDraft(Builder $query): void
    {
        $query->whereNull('removed_from_draft_at');
    }
```

Importe: `use App\Gallery\Models\GallerySet;`, `use Illuminate\Database\Eloquent\Builder;`

In `apps/studio/database/factories/Media/AssetFactory.php` die `definition()` um `'gallery_set_id' => null` erweitern und eine Zustandsmethode ergänzen, die das Set der Galerie zieht — einfacher: in der `definition()`

```php
'gallery_set_id' => GallerySet::factory(),
```

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GallerySetTest.php`
Expected: PASS, 3 Tests

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000200_add_set_and_draft_state_to_assets_table.php apps/studio/app/Media/Models/Asset.php apps/studio/database/factories/Media/AssetFactory.php apps/studio/tests/Feature/Gallery/GallerySetTest.php
git commit -m "feat(gallery): Bilder tragen Set und Entwurfsstatus"
```

---

## Task 3: Neue Spalten an `galleries`

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000300_add_cover_date_and_publication_to_galleries_table.php`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryDateTest.php`

**Interfaces:**
- Produces: `galleries.cover_asset_id`, `cover_focal_x`, `cover_focal_y`, `event_date`, `unpublished_at`. `Gallery::coverAsset(): BelongsTo`, `Gallery::sets(): HasMany`, `Gallery::assets(): HasMany` (Zeilen, nicht json — die json-Spalte fällt erst in Task 7, bis dahin heißt die Relation `assetRows()`).

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GalleryDateTest.php`

```php
<?php

use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('carries its own date only while no shoot answers the question', function () {
    $gallery = Gallery::factory()->create(['shoot_id' => null, 'event_date' => '2026-08-22']);

    expect($gallery->fresh()->event_date->toDateString())->toBe('2026-08-22');
});

it('refuses a date beside a shoot', function () {
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->create();

    expect(fn () => Gallery::factory()->for($project)->create([
        'shoot_id' => $shoot->id,
        'event_date' => '2026-08-22',
    ]))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryDateTest.php`
Expected: FAIL, `Column not found: event_date`

- [ ] **Step 3: Migration schreiben**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cover_asset_id ersetzt das feste erste Bild, das Vorschau und Portal
     * bisher nahmen. event_date trägt das Datum einer Galerie, an der kein
     * Shooting hängt; die Constraint verhindert die zweite Datumswahrheit,
     * ohne dass eine App-Regel sie tragen muss. unpublished_at schließt das
     * Gate, ohne eine Version anzutasten.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('cover_asset_id')->nullable()->after('assets')
                ->constrained('assets')->nullOnDelete();
            $table->unsignedSmallInteger('cover_focal_x')->nullable()->after('cover_asset_id');
            $table->unsignedSmallInteger('cover_focal_y')->nullable()->after('cover_focal_x');

            $table->date('event_date')->nullable()->after('shoot_id');
            $table->timestampTz('unpublished_at')->nullable()->after('expires_at');
        });

        DB::statement(<<<'SQL'
            alter table galleries add constraint galleries_one_date
                check (shoot_id is null or event_date is null)
        SQL);
    }

    public function down(): void
    {
        DB::statement('alter table galleries drop constraint galleries_one_date');

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_asset_id');
            $table->dropColumn(['cover_focal_x', 'cover_focal_y', 'event_date', 'unpublished_at']);
        });
    }
};
```

- [ ] **Step 4: Modell erweitern**

In `apps/studio/app/Gallery/Models/Gallery.php`:

`$fillable` um `'event_date'`, `'cover_asset_id'`, `'cover_focal_x'`, `'cover_focal_y'` erweitern. `unpublished_at` bleibt draußen, es wird nur über die Action aus Task 12 gesetzt.

`casts()` ergänzen: `'event_date' => 'immutable_date'`, `'unpublished_at' => 'immutable_datetime'`.

Relationen ergänzen:

```php
    public function sets(): HasMany
    {
        return $this->hasMany(GallerySet::class)->orderBy('position');
    }

    public function assetRows(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function coverAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'cover_asset_id');
    }

    /** Was auf der Karte steht: das Shooting antwortet, sonst die Galerie selbst. */
    public function occursOn(): ?CarbonImmutable
    {
        return $this->shoot?->date ?? $this->event_date;
    }
```

Importe: `use App\Media\Models\Asset;`, `use Carbon\CarbonImmutable;`

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryDateTest.php`
Expected: PASS, 2 Tests

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000300_add_cover_date_and_publication_to_galleries_table.php apps/studio/app/Gallery/Models/Gallery.php apps/studio/tests/Feature/Gallery/GalleryDateTest.php
git commit -m "feat(gallery): Titelbild, Datum und Ruecknahme als Spalten"
```

---

## Task 4: Upload kennt sein Ziel-Set

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000400_add_target_set_to_multipart_uploads_table.php`
- Modify: `apps/studio/app/Media/Models/MultipartUpload.php`
- Modify: `apps/studio/app/Media/Actions/StartOrResumeMultipartUpload.php`
- Modify: `apps/studio/app/Media/Http/Controllers/MultipartUploadController.php:36-62`
- Modify: `apps/studio/app/Media/Actions/IngestCompletedUpload.php:126-141`
- Modify: `apps/studio/resources/js/admin/lib/multipartUpload.ts`
- Test: `apps/studio/tests/Feature/Gallery/GalleryIngestTargetTest.php`

**Interfaces:**
- Consumes: `GallerySet` (Task 1), `Asset::$fillable['gallery_set_id']` (Task 2).
- Produces: `MultipartUploadController::store()` nimmt zusätzlich `gallery_set_id` (public_id des Sets, required). `StartOrResumeMultipartUpload::__invoke(Gallery $gallery, GallerySet $set, string $filename, int $fileSize, CarbonInterface $modifiedAt, ?string $contentType)`. Client: `uploadFile(url, file, onProgress, setPublicId)`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GalleryIngestTargetTest.php`

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Actions\IngestCompletedUpload;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('starts an upload against a named set', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::factory()->for($gallery)->create(['name' => 'Fotobox', 'position' => 1]);

    $this->postJson("/galerien/{$gallery->public_id}/uploads", [
        'gallery_set_id' => $set->public_id,
        'filename' => 'IMG_0001.jpg',
        'file_size' => 5_000_000,
        'file_modified_at' => 1_760_000_000_000,
    ])->assertOk();

    expect(MultipartUpload::first()->gallery_set_id)->toBe($set->id);
});

it('files the ingested asset into the set the upload named', function () {
    $gallery = Gallery::factory()->create();
    $set = GallerySet::factory()->for($gallery)->create(['name' => 'Trauung', 'position' => 1]);

    $upload = MultipartUpload::factory()->for($gallery)->create([
        'gallery_set_id' => $set->id,
        'status' => MultipartUploadStatus::Completed,
    ]);

    app(IngestCompletedUpload::class)($upload);

    expect(Asset::first()->gallery_set_id)->toBe($set->id);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryIngestTargetTest.php`
Expected: FAIL, `Column not found: gallery_set_id` auf `multipart_uploads`

- [ ] **Step 3: Migration und Modell**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wohin die Bytes gehören, steht beim Start der Sitzung fest und nicht
     * erst beim Ingest — der Worker weiß sonst nicht, welches Fach der
     * Mensch offen hatte, und die Wiederaufnahme nach einem Reload träfe ein
     * anderes.
     */
    public function up(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('gallery_set_id')->nullable()->after('gallery_id')
                ->constrained('gallery_sets')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            update multipart_uploads u
               set gallery_set_id = s.id
              from gallery_sets s
             where s.gallery_id = u.gallery_id
               and s.position = 0
        SQL);
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_set_id');
        });
    }
};
```

`MultipartUpload::$fillable` um `'gallery_set_id'` erweitern.

- [ ] **Step 4: Action, Controller und Ingest anpassen**

`StartOrResumeMultipartUpload::__invoke()` bekommt `GallerySet $set` als zweiten Parameter und schreibt `'gallery_set_id' => $set->id` in das `create()`-Array. Die Fingerabdruck-Abfrage bleibt auf `gallery_id` und `filename` — dieselbe Datei in einem anderen Fach ist derselbe Upload.

`MultipartUploadController::store()`:

```php
        $validated = $request->validate([
            'gallery_set_id' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'file_modified_at' => ['required', 'integer', 'min:0'],
            'content_type' => ['nullable', 'string', 'max:255'],
        ]);

        $set = $gallery->sets()->where('public_id', $validated['gallery_set_id'])->firstOrFail();

        ['upload' => $upload, 'parts' => $parts] = ($this->startOrResume)(
            $gallery,
            $set,
            $validated['filename'],
            $validated['file_size'],
            Carbon::createFromTimestampMs($validated['file_modified_at']),
            $validated['content_type'] ?? null,
        );
```

`IngestCompletedUpload::process()` — im `Asset::create([...])` ergänzen:

```php
            'gallery_set_id' => $upload->gallery_set_id,
```

und im Fehlerzweig (`Asset::firstOrNew`) das `fill()` ebenfalls um `'gallery_set_id' => $upload->gallery_set_id` erweitern.

- [ ] **Step 5: Client anpassen**

In `apps/studio/resources/js/admin/lib/multipartUpload.ts` bekommt `uploadFile` einen weiteren Parameter und reicht ihn in den Startaufruf:

```ts
export async function uploadFile(
  startUrl: string,
  file: File,
  onProgress: (progress: { partsDone: number; partsTotal: number }) => void,
  gallerySetId: string,
): Promise<void> {
```

Im Rumpf den Startaufruf um `gallery_set_id: gallerySetId` neben `fingerprintOf(file)` erweitern.

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryIngestTargetTest.php`
Expected: PASS, 2 Tests

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Media`
Expected: PASS — die vorhandenen Upload-Tests müssen mitgezogen werden, sie rufen `StartOrResumeMultipartUpload` direkt auf.

- [ ] **Step 7: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000400_add_target_set_to_multipart_uploads_table.php apps/studio/app/Media apps/studio/resources/js/admin/lib/multipartUpload.ts apps/studio/tests/Feature/Gallery/GalleryIngestTargetTest.php apps/studio/tests/Feature/Media
git commit -m "feat(media): Upload kennt sein Ziel-Set"
```

---

## Task 5: Version schnappt Zeilen, Gestaltung und Titelbild

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000500_add_design_and_cover_to_gallery_versions_table.php`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php:127-155`
- Modify: `apps/studio/app/Gallery/Models/GalleryVersion.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryVersioningTest.php` (erweitert)

**Interfaces:**
- Consumes: `Gallery::assetRows()`, `Gallery::sets()`, `Gallery::coverAsset()` (Task 3), `Asset::inDraft()` (Task 2).
- Produces: `Gallery::draftAssets(): Collection` — die Entwurfsbilder in Set- und Sortierreihenfolge, je Eintrag `['key' => string, 'set' => string, 'width' => ?int, 'height' => ?int]`. `GalleryVersion::$fillable` um `design` und `cover_key`.

- [ ] **Step 1: Failing test schreiben**

Anhängen an `apps/studio/tests/Feature/Gallery/GalleryVersioningTest.php`

```php
it('snapshots the rows, the design and the cover', function () {
    $gallery = Gallery::factory()->create(['design' => ['cover' => 'stripe', 'colour' => 'dark', 'grid' => 'dense', 'font' => null]]);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $first = Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg', 'taken_at' => '2026-08-22 10:00:00',
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg', 'taken_at' => '2026-08-22 09:00:00',
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $set->id,
        'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg', 'removed_from_draft_at' => now(),
    ]);

    $gallery->update(['cover_asset_id' => $first->id]);

    $version = $gallery->fresh()->publish();

    expect(collect($version->assets)->pluck('key')->all())
        ->toBe(['galleries/g/b.jpg', 'galleries/g/a.jpg'])
        ->and(collect($version->assets)->pluck('set')->unique()->all())->toBe(['Highlights'])
        ->and($version->cover_key)->toBe('galleries/g/a.jpg')
        ->and($version->design['colour'])->toBe('dark');
});
```

Importe ergänzen: `use App\Gallery\Models\GallerySet;`, `use App\Media\Models\Asset;`

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='snapshots the rows'`
Expected: FAIL, `Column not found: cover_key`

- [ ] **Step 3: Migration schreiben**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Doc-Kommentar der Versionstabelle behauptet Unveränderlichkeit
     * seit ihrem ersten Tag. Sie galt nur für Titel und Bilder: Gestaltung
     * und Titelbild las die Auslieferung live aus galleries, ein
     * veröffentlichter Stand änderte sich also rückwirkend. Beides gehört in
     * den Schnappschuss.
     */
    public function up(): void
    {
        Schema::table('gallery_versions', function (Blueprint $table) {
            $table->jsonb('design')->nullable()->after('assets');
            $table->string('cover_key')->nullable()->after('design');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_versions', function (Blueprint $table) {
            $table->dropColumn(['design', 'cover_key']);
        });
    }
};
```

`GalleryVersion::$fillable` um `'design'` und `'cover_key'` erweitern, `casts()` um `'design' => 'array'`.

- [ ] **Step 4: `draftAssets()` und `publish()` umstellen**

In `Gallery.php` ergänzen:

```php
    /**
     * Die Entwurfsbilder in der Reihenfolge, in der sie ausgeliefert würden:
     * Sets in ihrer Position, innerhalb eines Sets nach dessen eigener
     * Regel. Fehlt taken_at — gescannt, exportiert, ohne EXIF —, traegt der
     * Dateiname die Reihenfolge; created_at waere die Reihenfolge, in der
     * die Queue fertig wurde, und die ist bei parallelen Workern Zufall.
     */
    public function draftAssets(): Collection
    {
        return $this->sets()->with(['assets' => fn ($query) => $query->inDraft()])->get()
            ->flatMap(fn (GallerySet $set) => $set->assets
                ->sortBy([
                    [$set->sort_key, $set->sort_dir],
                    ['filename', 'asc'],
                ])
                ->map(fn (Asset $asset) => [
                    'key' => $asset->key,
                    'set' => $set->name,
                    'width' => $asset->width,
                    'height' => $asset->height,
                ]))
            ->values();
    }
```

Importe: `use Illuminate\Support\Collection;`

In `publish()` das `versions()->create([...])` ersetzen:

```php
            return $this->versions()->create([
                'version' => $next,
                'title' => $this->title,
                'assets' => $this->draftAssets()->all(),
                'design' => $this->design,
                'cover_key' => $this->coverAsset?->key,
                'published_at' => now(),
            ]);
```

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryVersioningTest.php`
Expected: PASS

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery`
Expected: Fehlschläge in Tests, die `assets` als json setzen und dann veröffentlichen — die werden in Task 7 umgestellt. Notiere die Namen, gehe weiter.

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000500_add_design_and_cover_to_gallery_versions_table.php apps/studio/app/Gallery/Models apps/studio/tests/Feature/Gallery/GalleryVersioningTest.php
git commit -m "feat(gallery): Version schnappt Gestaltung und Titelbild"
```

---

## Task 6: Record-Controller liest Zeilen und Sets

**Files:**
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryRecordController.php:44-120`
- Modify: `apps/studio/app/Gallery/Data/GalleryRowData.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryRecordTest.php` (umgestellt)

**Interfaces:**
- Consumes: `Gallery::sets()`, `Gallery::draftAssets()`, `Asset::inDraft()`.
- Produces: Inertia-Props `sets: {id, name, count, sortKey, sortDir}[]`, `assets: {id, key, setId, src, filename, width, height, isCover}[]`, `figures.assets` = Anzahl der Entwurfsbilder. `GalleryRowData` bekommt `coverSrc: ?string`, `occursOn: ?string`, `status: string`.

- [ ] **Step 1: Test umstellen**

In `apps/studio/tests/Feature/Gallery/GalleryRecordTest.php` die Hilfsfunktion `managedGallery()` ersetzen:

```php
function managedGallery(): Gallery
{
    $gallery = Gallery::factory()->for(Project::factory())->create(['title' => 'Hochzeitstag']);
    $highlights = GallerySet::where('gallery_id', $gallery->id)->first();
    $trauung = GallerySet::factory()->for($gallery)->create(['name' => 'Trauung', 'position' => 1]);

    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $trauung->id,
        'key' => 'galleries/g1/a.jpg', 'filename' => 'a.jpg', 'width' => 4000, 'height' => 3000,
    ]);
    Asset::factory()->create([
        'gallery_id' => $gallery->id, 'gallery_set_id' => $highlights->id,
        'key' => 'galleries/g1/b.jpg', 'filename' => 'b.jpg', 'width' => 4000, 'height' => 3000,
    ]);

    $gallery->publish();

    return $gallery->fresh();
}
```

und den ersten Test ersetzen:

```php
it('lists the sets with their counts', function () {
    $gallery = managedGallery();

    $props = props("/galerien/{$gallery->public_id}");

    expect(collect($props['sets'])->pluck('name')->all())->toBe(['Highlights', 'Trauung'])
        ->and(collect($props['sets'])->firstWhere('name', 'Trauung')['count'])->toBe(1)
        ->and($props['figures']['assets'])->toBe(2);
});
```

Importe: `use App\Gallery\Models\GallerySet;`, `use App\Media\Models\Asset;`

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='lists the sets with their counts'`
Expected: FAIL — `sets` trägt noch die aus der json-Spalte gruppierten Namen

- [ ] **Step 3: Controller umstellen**

In `GalleryRecordController::show()` den Block ab `$assets = collect($gallery->assets ?? []);` ersetzen:

```php
        $version = $gallery->currentVersion;
        $sets = $gallery->sets()->withCount(['assets' => fn ($query) => $query->inDraft()])->get();
        $assets = $gallery->assetRows()->inDraft()->with('set')->get();
```

und die beiden Prop-Blöcke:

```php
            'sets' => $sets->map(fn ($set) => [
                'id' => $set->public_id,
                'name' => $set->name,
                'count' => $set->assets_count,
                'sortKey' => $set->sort_key,
                'sortDir' => $set->sort_dir,
            ])->all(),
            'assets' => $assets->map(fn (Asset $asset) => [
                'id' => $asset->public_id,
                'key' => $asset->key,
                'setId' => $asset->set->public_id,
                'filename' => $asset->filename,
                'width' => $asset->width,
                'height' => $asset->height,
                'isCover' => $asset->id === $gallery->cover_asset_id,
                'src' => $version === null ? null : "/galerie-bilder/{$version->public_id}/{$asset->key}",
            ])->all(),
```

`figures.assets` wird `$assets->count()`.

- [ ] **Step 4: `GalleryRowData` umstellen**

```php
    public static function fromModel(Gallery $gallery): self
    {
        return new self(
            id: $gallery->public_id,
            title: $gallery->title,
            projectName: $gallery->project?->name,
            assetCount: $gallery->assetRows()->inDraft()->count(),
            version: $gallery->currentVersion?->version,
            expiresAt: $gallery->expires_at?->format('Y-m-d'),
            occursOn: $gallery->occursOn()?->format('Y-m-d'),
            status: match (true) {
                $gallery->is_external => 'external',
                $gallery->unpublished_at !== null => 'withdrawn',
                $gallery->currentVersion === null => 'draft',
                $gallery->expires_at !== null && $gallery->expires_at->isPast() => 'expired',
                default => 'published',
            },
            coverSrc: $gallery->coverAsset === null || $gallery->currentVersion === null
                ? null
                : "/galerie-bilder/{$gallery->currentVersion->public_id}/{$gallery->coverAsset->key}",
        );
    }
```

Konstruktor um die drei neuen Felder erweitern.

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryRecordTest.php tests/Feature/Gallery/GalleryIndexTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Gallery/Http/Controllers/GalleryRecordController.php apps/studio/app/Gallery/Data/GalleryRowData.php apps/studio/tests/Feature/Gallery/GalleryRecordTest.php
git commit -m "feat(gallery): Record liest Zeilen statt json"
```

---

## Task 7: `galleries.assets` fällt

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000600_drop_assets_json_from_galleries_table.php`
- Modify: `apps/studio/app/Gallery/Models/Gallery.php`
- Modify: `apps/studio/app/Gallery/Actions/ImportLegacyGallery.php:110-126`
- Modify: `apps/studio/database/factories/Gallery/GalleryFactory.php`
- Modify: `apps/studio/database/seeders/E2eSeeder.php:330-345`
- Test: alle vorhandenen unter `apps/studio/tests/Feature/Gallery` und `tests/Feature/Portal`

**Interfaces:**
- Consumes: alles aus Task 1–6.
- Produces: `Gallery` ohne `assets`-Attribut. `Gallery::booted()` legt beim Anlegen einer nicht-externen Galerie ein Set „Highlights" an.

- [ ] **Step 1: Failing test schreiben**

Anhängen an `apps/studio/tests/Feature/Gallery/GallerySetTest.php`

```php
it('gives every fresh gallery one set to put pictures in', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->sets()->pluck('name')->all())->toBe(['Highlights']);
});

it('gives an external gallery none, it holds no pictures', function () {
    $gallery = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://example.test/g']);

    expect($gallery->sets()->count())->toBe(0);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='gives every fresh gallery one set'`
Expected: FAIL, `expect([])->toBe(['Highlights'])`

- [ ] **Step 3: Standard-Set beim Anlegen**

In `Gallery.php`:

```php
    protected static function booted(): void
    {
        static::created(function (Gallery $gallery) {
            if (! $gallery->is_external) {
                $gallery->sets()->create(['name' => 'Highlights', 'position' => 0]);
            }
        });
    }
```

- [ ] **Step 4: Spalte fallen lassen und Schreiber umstellen**

Migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die zweite Ablage fällt. Sie wurde nur von ImportLegacyGallery und dem
     * E2eSeeder beschrieben, während der Upload Asset-Zeilen anlegte — eine
     * hochgeladene Galerie zeigte deshalb nie ein Bild. Kein Rückfüllen:
     * bestehende Entwurfslisten gehen verloren, veröffentlichte Versionen
     * tragen ihren Schnappschuss selbst.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('assets');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->json('assets')->default('[]');
        });
    }
};
```

`Gallery::$fillable`, `$attributes` und `casts()` um `assets` bereinigen.

`GalleryFactory::definition()` — `'assets' => []` streichen.

`ImportLegacyGallery::backfill()` — statt `'assets' => $assets` in `Gallery::create()` nach dem Anlegen die Zeilen schreiben:

```php
        $set = $gallery->sets()->first();

        foreach ($assets as $entry) {
            $gallery->assetRows()->create([
                'gallery_set_id' => $set->id,
                'status' => AssetStatus::Ready,
                'key' => $entry['key'],
                'filename' => basename($entry['key']),
                'width' => $entry['width'] ?? null,
                'height' => $entry['height'] ?? null,
            ]);
        }

        $gallery->fresh()->publish();
```

Import: `use App\Media\Enums\AssetStatus;`

`E2eSeeder` an derselben Stelle analog: erst `Gallery::create([...])` ohne `assets`, dann je Eintrag eine Zeile im ersten Set.

- [ ] **Step 5: Alle Tests umstellen und laufen lassen**

Jeder Test, der `'assets' => [...]` an `Gallery::factory()` übergibt, legt stattdessen `Asset::factory()` im Set der Galerie an. Betroffen laut Task 5 Schritt 5: `HomeOverviewTest`, `GalleryReadApiTest`, `GalleryViewsTest`, `GalleryVersioningTest`, `GalleryGridTest`, `BuildGalleryZipTest`, `GalleryListsTest`.

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest`
Expected: PASS, gesamte Suite

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database apps/studio/app/Gallery apps/studio/tests
git commit -m "refactor(gallery): zweite Bilderablage entfernt"
```

---

## Task 8: Sets anlegen, umbenennen, sortieren, löschen

**Files:**
- Create: `apps/studio/app/Gallery/Http/Controllers/GallerySetController.php`
- Create: `apps/studio/app/Gallery/Actions/DeleteGallerySet.php`
- Modify: `apps/studio/routes/admin.php:450-470`
- Test: `apps/studio/tests/Feature/Gallery/GallerySetCrudTest.php`

**Interfaces:**
- Consumes: `GallerySet`, `Asset::inDraft()`.
- Produces: Routen `POST /galerien/{gallery}/sets`, `PATCH /galerien/{gallery}/sets/{gallerySet}`, `DELETE /galerien/{gallery}/sets/{gallerySet}`, `PATCH /galerien/{gallery}/sets/reihenfolge`. `DeleteGallerySet::__invoke(GallerySet $set, ?GallerySet $target)` — `null` heißt: Inhalt mitlöschen.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GallerySetCrudTest.php`

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->highlights = GallerySet::where('gallery_id', $this->gallery->id)->first();
});

it('adds a set behind the last one', function () {
    $this->post("/galerien/{$this->gallery->public_id}/sets", ['name' => 'Feier'])->assertRedirect();

    expect($this->gallery->sets()->pluck('name')->all())->toBe(['Highlights', 'Feier'])
        ->and($this->gallery->sets()->where('name', 'Feier')->first()->position)->toBe(1);
});

it('refuses a name the gallery already carries', function () {
    $this->post("/galerien/{$this->gallery->public_id}/sets", ['name' => 'Highlights'])
        ->assertSessionHasErrors('name');
});

it('renames a set', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}", ['name' => 'Beste'])
        ->assertRedirect();

    expect($this->highlights->fresh()->name)->toBe('Beste');
});

it('moves the pictures of a deleted set into the named target', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $feier->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}", [
        'target_set_id' => $this->highlights->public_id,
    ])->assertRedirect();

    expect($asset->fresh()->gallery_set_id)->toBe($this->highlights->id)
        ->and(GallerySet::whereKey($feier->id)->exists())->toBeFalse();
});

it('takes the pictures with it when no target is named', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $feier->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}")->assertRedirect();

    expect(Asset::whereKey($asset->id)->exists())->toBeFalse();
});

it('refuses to delete the last set', function () {
    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}")
        ->assertSessionHasErrors();
});

it('reorders the sets', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);

    $this->patch("/galerien/{$this->gallery->public_id}/sets/reihenfolge", [
        'order' => [$feier->public_id, $this->highlights->public_id],
    ])->assertRedirect();

    expect($this->gallery->sets()->pluck('name')->all())->toBe(['Feier', 'Highlights']);
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GallerySetCrudTest.php`
Expected: FAIL, 404 auf allen Routen

- [ ] **Step 3: Action schreiben**

`apps/studio/app/Gallery/Actions/DeleteGallerySet.php`

```php
<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\GallerySet;
use Illuminate\Support\Facades\DB;

/**
 * Ein Set mit 64 Bildern zu löschen ist zwei Handlungen, nicht einer: das
 * Fach verschwindet, und sein Inhalt geht irgendwohin. Der Aufrufer muss
 * sagen, wohin — stillschweigendes Verschieben wäre nur in dem einen Fall
 * harmlos, in dem das Fach ohnehin leer ist.
 */
class DeleteGallerySet
{
    public function __invoke(GallerySet $set, ?GallerySet $target): void
    {
        DB::transaction(function () use ($set, $target) {
            if ($target !== null) {
                $set->assets()->update(['gallery_set_id' => $target->id]);
            }

            // cascadeOnDelete raeumt ohne Ziel den Inhalt mit ab.
            $set->delete();
        });
    }
}
```

- [ ] **Step 4: Controller und Routen schreiben**

`apps/studio/app/Gallery/Http/Controllers/GallerySetController.php`

```php
<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\DeleteGallerySet;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GallerySetController extends Controller
{
    public function store(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('gallery_sets', 'name')
                ->where('gallery_id', $gallery->id)],
        ]);

        $gallery->sets()->create([
            'name' => $validated['name'],
            'position' => 1 + (int) $gallery->sets()->max('position'),
        ]);

        return back();
    }

    public function update(Request $request, Gallery $gallery, GallerySet $gallerySet): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('gallery_sets', 'name')
                ->where('gallery_id', $gallery->id)->ignore($gallerySet->id)],
            'sort_key' => ['sometimes', Rule::in(['taken_at', 'filename'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $gallerySet->update($validated);

        return back();
    }

    public function reorder(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string'],
        ]);

        foreach ($validated['order'] as $position => $publicId) {
            $gallery->sets()->where('public_id', $publicId)->update(['position' => $position]);
        }

        return back();
    }

    public function destroy(Request $request, Gallery $gallery, GallerySet $gallerySet, DeleteGallerySet $delete): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        if ($gallery->sets()->count() === 1) {
            throw ValidationException::withMessages(['set' => __('galleryRecord.lastSet')]);
        }

        $validated = $request->validate([
            'target_set_id' => ['nullable', 'string'],
        ]);

        $target = ($validated['target_set_id'] ?? null) === null
            ? null
            : $gallery->sets()->where('public_id', $validated['target_set_id'])->firstOrFail();

        $delete($gallerySet, $target);

        return back();
    }
}
```

In `routes/admin.php` innerhalb derselben Gruppe wie `galleries.index`:

```php
        Route::post('/galerien/{gallery}/sets', [GallerySetController::class, 'store'])
            ->name('galleries.sets.store');
        Route::patch('/galerien/{gallery}/sets/reihenfolge', [GallerySetController::class, 'reorder'])
            ->name('galleries.sets.reorder');
        Route::patch('/galerien/{gallery}/sets/{gallerySet}', [GallerySetController::class, 'update'])
            ->name('galleries.sets.update');
        Route::delete('/galerien/{gallery}/sets/{gallerySet}', [GallerySetController::class, 'destroy'])
            ->name('galleries.sets.destroy');
```

`/sets/reihenfolge` steht über `/sets/{gallerySet}`, sonst schluckt die Bindung das Wort als public_id.

- [ ] **Step 5: Locale-Schlüssel ergänzen**

In `apps/studio/resources/js/locales/de.json` unter `galleryRecord`:

```json
"lastSet": "Die Galerie braucht ein Set.",
"addSet": "Set",
"deleteSet": "Set löschen",
"moveTo": "Bilder verschieben nach",
"deleteWithContents": "Mit Inhalt löschen"
```

- [ ] **Step 6: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GallerySetCrudTest.php`
Expected: PASS, 7 Tests

- [ ] **Step 7: Commit**

```bash
git add apps/studio/app/Gallery apps/studio/routes/admin.php apps/studio/resources/js/locales/de.json apps/studio/tests/Feature/Gallery/GallerySetCrudTest.php
git commit -m "feat(gallery): Sets anlegen, umbenennen, loeschen"
```

---

## Task 9: Bilder verschieben, entfernen, Titelbild setzen

**Files:**
- Create: `apps/studio/app/Gallery/Http/Controllers/GalleryAssetController.php`
- Modify: `apps/studio/routes/admin.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryAssetActionsTest.php`

**Interfaces:**
- Consumes: `Asset::inDraft()`, `Gallery::coverAsset()`.
- Produces: `POST /galerien/{gallery}/bilder/verschieben` (`asset_ids[]`, `gallery_set_id`), `DELETE /galerien/{gallery}/bilder` (`asset_ids[]`), `PATCH /galerien/{gallery}/titelbild` (`asset_id`, `focal_x`, `focal_y`), `GET /galerien/{gallery}/bilder/{asset}/original`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GalleryAssetActionsTest.php`

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->highlights = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $this->feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
});

it('moves a selection into another set', function () {
    $one = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $two = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->post("/galerien/{$this->gallery->public_id}/bilder/verschieben", [
        'asset_ids' => [$one->public_id, $two->public_id],
        'gallery_set_id' => $this->feier->public_id,
    ])->assertRedirect();

    expect($one->fresh()->gallery_set_id)->toBe($this->feier->id)
        ->and($two->fresh()->gallery_set_id)->toBe($this->feier->id);
});

it('takes a picture out of the draft without touching the row', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/bilder", ['asset_ids' => [$asset->public_id]])
        ->assertRedirect();

    expect($asset->fresh()->removed_from_draft_at)->not->toBeNull()
        ->and(Asset::whereKey($asset->id)->exists())->toBeTrue();
});

it('sets the cover and its focal point', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", [
        'asset_id' => $asset->public_id,
        'focal_x' => 58,
        'focal_y' => 34,
    ])->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBe($asset->id)
        ->and($this->gallery->fresh()->cover_focal_x)->toBe(58);
});

it('drops the focal point when the cover changes', function () {
    $first = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $second = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", [
        'asset_id' => $first->public_id, 'focal_x' => 58, 'focal_y' => 34,
    ]);
    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", ['asset_id' => $second->public_id]);

    expect($this->gallery->fresh()->cover_focal_x)->toBeNull();
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryAssetActionsTest.php`
Expected: FAIL, 404

- [ ] **Step 3: Controller schreiben**

```php
<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryAssetController extends Controller
{
    public function move(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_ids' => ['required', 'array'],
            'asset_ids.*' => ['string'],
            'gallery_set_id' => ['required', 'string'],
        ]);

        $set = $gallery->sets()->where('public_id', $validated['gallery_set_id'])->firstOrFail();

        $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])
            ->update(['gallery_set_id' => $set->id]);

        return back();
    }

    /**
     * Nimmt das Bild aus dem Entwurf, nicht aus der Welt: Zeile und Objekt
     * bleiben, weil BuildGalleryZip die Keys bereits veröffentlichter
     * Versionen über genau diese Zeilen auflöst.
     */
    public function remove(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_ids' => ['required', 'array'],
            'asset_ids.*' => ['string'],
        ]);

        $removed = $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])->get();

        $gallery->assetRows()->whereIn('public_id', $validated['asset_ids'])
            ->update(['removed_from_draft_at' => now()]);

        if ($removed->contains(fn (Asset $asset) => $asset->id === $gallery->cover_asset_id)) {
            $gallery->update(['cover_asset_id' => null, 'cover_focal_x' => null, 'cover_focal_y' => null]);
        }

        return back();
    }

    public function cover(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'asset_id' => ['required', 'string'],
            'focal_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'focal_y' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $asset = $gallery->assetRows()->inDraft()->where('public_id', $validated['asset_id'])->firstOrFail();
        $changed = $asset->id !== $gallery->cover_asset_id;

        $gallery->update([
            'cover_asset_id' => $asset->id,
            // Ein Fokuspunkt gehört zu einem Bild; auf einem anderen zeigt er ins Leere.
            'cover_focal_x' => $changed ? ($validated['focal_x'] ?? null) : ($validated['focal_x'] ?? $gallery->cover_focal_x),
            'cover_focal_y' => $changed ? ($validated['focal_y'] ?? null) : ($validated['focal_y'] ?? $gallery->cover_focal_y),
        ]);

        return back();
    }

    /** Eine kurzlebige signierte URL, nie das Objekt selbst — sonst haengt ein Worker an einem 40-MB-RAW. */
    public function original(Gallery $gallery, Asset $asset): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        abort_unless($asset->gallery_id === $gallery->id, 404);

        return redirect()->away(Storage::disk('s3')->temporaryUrl($asset->key, now()->addMinutes(5)));
    }
}
```

Routen (über `/galerien/{gallery}` bindet Laravel korrekt, Reihenfolge unkritisch):

```php
        Route::post('/galerien/{gallery}/bilder/verschieben', [GalleryAssetController::class, 'move'])
            ->name('galleries.assets.move');
        Route::delete('/galerien/{gallery}/bilder', [GalleryAssetController::class, 'remove'])
            ->name('galleries.assets.remove');
        Route::get('/galerien/{gallery}/bilder/{asset}/original', [GalleryAssetController::class, 'original'])
            ->name('galleries.assets.original');
        Route::patch('/galerien/{gallery}/titelbild', [GalleryAssetController::class, 'cover'])
            ->name('galleries.cover');
```

- [ ] **Step 4: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryAssetActionsTest.php`
Expected: PASS, 5 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Gallery/Http/Controllers/GalleryAssetController.php apps/studio/routes/admin.php apps/studio/tests/Feature/Gallery/GalleryAssetActionsTest.php
git commit -m "feat(gallery): Bilder verschieben, entfernen, Titelbild"
```

---

## Task 10: Duplikate vor dem Upload

**Files:**
- Create: `apps/studio/app/Media/Http/Controllers/UploadPrecheckController.php`
- Modify: `apps/studio/routes/admin.php`
- Modify: `apps/studio/app/Media/Actions/IngestCompletedUpload.php`
- Modify: `apps/studio/app/Media/Http/Controllers/MultipartUploadController.php`
- Create: `apps/studio/database/migrations/2026_08_24_000700_add_replaces_asset_to_multipart_uploads_table.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryDuplicateTest.php`

**Interfaces:**
- Consumes: Task 4.
- Produces: `POST /galerien/{gallery}/uploads/pruefen` nimmt `files: [{filename, size}]` und antwortet `{duplicates: [{filename, size, assetId}]}`. `MultipartUploadController::store()` nimmt zusätzlich `replaces_asset_id` (optional). Beim Ingest erbt die neue Zeile Set und Titelbild-Rolle der ersetzten, die alte bekommt `removed_from_draft_at`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GalleryDuplicateTest.php`

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->set = GallerySet::where('gallery_id', $this->gallery->id)->first();
});

it('names the files the gallery already holds', function () {
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [
            ['filename' => 'IMG_0001.jpg', 'size' => 5_460_000],
            ['filename' => 'IMG_0002.jpg', 'size' => 5_480_000],
        ],
    ])->assertOk();

    expect($response->json('duplicates'))->toHaveCount(1)
        ->and($response->json('duplicates.0.filename'))->toBe('IMG_0001.jpg');
});

it('looks across the whole gallery, not one set', function () {
    $other = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $other->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [['filename' => 'IMG_0001.jpg', 'size' => 5_460_000]],
    ])->assertOk();

    expect($response->json('duplicates'))->toHaveCount(1);
});

it('ignores what already left the draft', function () {
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000, 'removed_from_draft_at' => now(),
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [['filename' => 'IMG_0001.jpg', 'size' => 5_460_000]],
    ])->assertOk();

    expect($response->json('duplicates'))->toBeEmpty();
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryDuplicateTest.php`
Expected: FAIL, 404

- [ ] **Step 3: Controller und Route schreiben**

```php
<?php

namespace App\Media\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-02 erkennt dieselben Bytes ueber assets_gallery_checksum_unique — aber
 * erst, nachdem sie durchs Netz sind. Diese Abfrage kostet einen
 * Rundlauf und spart 853 MB.
 *
 * Name und Groesse, nicht der Inhalt: eine Pruefsumme im Browser liest jede
 * Datei einmal komplett von der Platte, bevor irgendetwas passiert.
 */
class UploadPrecheckController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery): JsonResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'files' => ['required', 'array'],
            'files.*.filename' => ['required', 'string', 'max:255'],
            'files.*.size' => ['required', 'integer', 'min:1'],
        ]);

        $wanted = collect($validated['files'])->keyBy(fn (array $file) => "{$file['filename']}:{$file['size']}");

        $duplicates = $gallery->assetRows()->inDraft()
            ->whereIn('filename', $wanted->pluck('filename'))
            ->get()
            ->filter(fn ($asset) => $wanted->has("{$asset->filename}:{$asset->byte_size}"))
            ->map(fn ($asset) => [
                'filename' => $asset->filename,
                'size' => $asset->byte_size,
                'assetId' => $asset->public_id,
            ])
            ->values();

        return response()->json(['duplicates' => $duplicates]);
    }
}
```

Route:

```php
        Route::post('/galerien/{gallery}/uploads/pruefen', UploadPrecheckController::class)
            ->name('galleries.uploads.precheck');
```

- [ ] **Step 4: Ersetzen-Weg schreiben**

Migration `2026_08_24_000700_add_replaces_asset_to_multipart_uploads_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wer eine nachbearbeitete Fassung nachliefert, will die Einordnung der
     * alten behalten — Fach und Titelbild-Rolle. Kein Ueberschreiben des
     * Keys: derselbe Key mit anderen Bytes bricht den Cache-Vertrag aus
     * GalleryImageController.
     */
    public function up(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->foreignId('replaces_asset_id')->nullable()->after('gallery_set_id')
                ->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('multipart_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_asset_id');
        });
    }
};
```

`MultipartUpload::$fillable` um `'replaces_asset_id'`; `MultipartUploadController::store()` validiert `'replaces_asset_id' => ['nullable', 'string']`, löst über `$gallery->assetRows()->where('public_id', ...)` auf und reicht die ID an `StartOrResumeMultipartUpload` durch.

In `IngestCompletedUpload::process()`, direkt vor `return $asset;`:

```php
        if ($upload->replaces_asset_id !== null) {
            $replaced = Asset::find($upload->replaces_asset_id);

            if ($replaced !== null) {
                $asset->update(['gallery_set_id' => $replaced->gallery_set_id]);
                $replaced->update(['removed_from_draft_at' => now()]);

                Gallery::where('cover_asset_id', $replaced->id)->update(['cover_asset_id' => $asset->id]);
            }
        }
```

Import: `use App\Gallery\Models\Gallery;`

Test dafür anhängen:

```php
it('lets a replacement inherit set and cover role', function () {
    $old = Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);
    $this->gallery->update(['cover_asset_id' => $old->id]);

    $upload = MultipartUpload::factory()->for($this->gallery)->create([
        'gallery_set_id' => $this->set->id,
        'replaces_asset_id' => $old->id,
        'status' => MultipartUploadStatus::Completed,
    ]);

    app(IngestCompletedUpload::class)($upload);

    $fresh = Asset::whereNull('removed_from_draft_at')->latest('id')->first();

    expect($fresh->gallery_set_id)->toBe($this->set->id)
        ->and($this->gallery->fresh()->cover_asset_id)->toBe($fresh->id)
        ->and($old->fresh()->removed_from_draft_at)->not->toBeNull();
});
```

Importe ergänzen: `use App\Media\Actions\IngestCompletedUpload;`, `use App\Media\Enums\MultipartUploadStatus;`, `use App\Media\Models\MultipartUpload;`

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryDuplicateTest.php`
Expected: PASS, 4 Tests

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Media apps/studio/database/migrations/2026_08_24_000700_add_replaces_asset_to_multipart_uploads_table.php apps/studio/routes/admin.php apps/studio/tests/Feature/Gallery/GalleryDuplicateTest.php
git commit -m "feat(media): Duplikate vor dem Upload erkennen"
```

---

## Task 11: Veröffentlichen und Zurücknehmen

**Files:**
- Create: `apps/studio/app/Gallery/Support/DraftDeviation.php`
- Create: `apps/studio/app/Gallery/Actions/PublishGallery.php`
- Create: `apps/studio/app/Gallery/Http/Controllers/GalleryPublicationController.php`
- Modify: `apps/studio/app/Policies/GalleryPolicy.php`
- Modify: `apps/studio/app/Http/Middleware/EnsureGalleryGate.php`
- Modify: `apps/studio/routes/admin.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryPublicationTest.php`

**Interfaces:**
- Consumes: `Gallery::draftAssets()`, `Gallery::publish()`.
- Produces: `DraftDeviation::of(Gallery $gallery): bool`, `PublishGallery::__invoke(Gallery $gallery): ?GalleryVersion` (null = nur freigegeben), `GalleryPolicy::publish(User $user, Gallery $gallery): bool`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/tests/Feature/Gallery/GalleryPublicationTest.php`

```php
<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
});

it('writes a first version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")->assertRedirect();

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1);
});

it('reopens the standing version when nothing moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1)
        ->and($this->gallery->fresh()->unpublished_at)->toBeNull();
});

it('writes a second version when the draft moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('sees a swap of equal count as a change', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    $this->gallery->assetRows()->update(['removed_from_draft_at' => now()]);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('closes the gate on withdrawal and leaves the version standing', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung")->assertRedirect();

    expect($this->gallery->fresh()->unpublished_at)->not->toBeNull()
        ->and($this->gallery->fresh()->versions()->count())->toBe(1);
});

it('keeps publishing away from a role that may not show things to clients', function () {
    $shooter = User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]);

    $this->actingAs($shooter, 'staff')
        ->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")
        ->assertForbidden();
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryPublicationTest.php`
Expected: FAIL, 404

- [ ] **Step 3: Abweichung und Action schreiben**

`apps/studio/app/Gallery/Support/DraftDeviation.php`

```php
<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;

/**
 * Ob der Entwurf von der stehenden Version abweicht — bei jedem Aufruf frisch
 * gerechnet statt in einer Spalte gepflegt, die eine kuenftige Action
 * vergessen kann und die dann still falsch steht.
 *
 * Nicht die Anzahl: ein Bild raus, eins rein ergibt dieselbe Zahl und einen
 * anderen Inhalt.
 */
class DraftDeviation
{
    public static function of(Gallery $gallery): bool
    {
        $version = $gallery->currentVersion;

        if ($version === null) {
            return true;
        }

        $draft = $gallery->draftAssets()->map(fn (array $entry) => "{$entry['set']}/{$entry['key']}")->all();
        $published = collect($version->assets)->map(fn (array $entry) => ($entry['set'] ?? '')."/{$entry['key']}")->all();

        return $draft !== $published
            || $gallery->title !== $version->title
            || $gallery->design !== $version->design
            || $gallery->coverAsset?->key !== $version->cover_key;
    }
}
```

`apps/studio/app/Gallery/Actions/PublishGallery.php`

```php
<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Gallery\Support\DraftDeviation;

/**
 * Ein Knopf, zwei Ausgänge. Eine Version, die sich in nichts von ihrer
 * Vorgaengerin unterscheidet, waere Rauschen in einer Historie, die ein Paar
 * ueber Jahre begleitet.
 */
class PublishGallery
{
    public function __invoke(Gallery $gallery): ?GalleryVersion
    {
        $version = DraftDeviation::of($gallery) ? $gallery->publish() : null;

        $gallery->forceFill(['unpublished_at' => null])->save();

        return $version;
    }
}
```

- [ ] **Step 4: Controller, Policy, Gate, Routen**

`GalleryPublicationController`:

```php
<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;

class GalleryPublicationController extends Controller
{
    public function store(Gallery $gallery, PublishGallery $publish): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $version = $publish($gallery);

        AuditLogger::record(
            event: 'gallery.published',
            actor: auth('staff')->user(),
            context: ['version' => $version?->version],
            subject: $gallery->public_id,
        );

        return back();
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $gallery->forceFill(['unpublished_at' => now()])->save();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }
}
```

`GalleryPolicy` ergänzen:

```php
    /**
     * ManagePortalAccess statt ManageShoots: die Faehigkeit entscheidet
     * laut ihrer eigenen Beschreibung bereits, ob eines der Projekte eines
     * Kontakts zeigbereit ist — eine Galerie freizugeben ist dieselbe Frage.
     */
    public function publish(User $user, Gallery $gallery): bool
    {
        return $user->role->allows(Capability::ManagePortalAccess);
    }
```

`EnsureGalleryGate` — vor jeder anderen Prüfung:

```php
        abort_if($gallery->unpublished_at !== null, 404);
```

Routen:

```php
        Route::post('/galerien/{gallery}/veroeffentlichen', [GalleryPublicationController::class, 'store'])
            ->name('galleries.publish');
        Route::delete('/galerien/{gallery}/veroeffentlichung', [GalleryPublicationController::class, 'destroy'])
            ->name('galleries.withdraw');
```

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryPublicationTest.php tests/Feature/Gallery/GalleryAccessGatesTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/app/Gallery apps/studio/app/Policies/GalleryPolicy.php apps/studio/app/Http/Middleware/EnsureGalleryGate.php apps/studio/routes/admin.php apps/studio/tests/Feature/Gallery/GalleryPublicationTest.php
git commit -m "feat(gallery): veroeffentlichen und zuruecknehmen"
```

---

## Task 12: Auswahllogik als reine Funktionen

**Files:**
- Create: `apps/studio/resources/js/admin/lib/gallerySelection.ts`
- Create: `apps/studio/resources/js/admin/lib/gallerySelection.test.ts`

**Interfaces:**
- Produces: `toggle(selected: Set<string>, id: string): Set<string>`, `range(ids: string[], anchor: string, target: string): string[]`, `applyClick(state, ids, id, modifiers): SelectionState` mit `SelectionState = { selected: Set<string>; anchor: string | null }`.

- [ ] **Step 1: Failing test schreiben**

`apps/studio/resources/js/admin/lib/gallerySelection.test.ts`

```ts
import { describe, expect, it } from 'vitest'
import { applyClick, range, toggle } from './gallerySelection'

const IDS = ['a', 'b', 'c', 'd', 'e']

describe('gallerySelection', () => {
  it('toggles one id', () => {
    expect([...toggle(new Set(['a']), 'b')]).toEqual(['a', 'b'])
    expect([...toggle(new Set(['a', 'b']), 'b')]).toEqual(['a'])
  })

  it('spans a range in either direction', () => {
    expect(range(IDS, 'b', 'd')).toEqual(['b', 'c', 'd'])
    expect(range(IDS, 'd', 'b')).toEqual(['b', 'c', 'd'])
  })

  it('replaces the selection on a plain click', () => {
    const next = applyClick({ selected: new Set(['a', 'b']), anchor: 'a' }, IDS, 'd', {})

    expect([...next.selected]).toEqual(['d'])
    expect(next.anchor).toBe('d')
  })

  it('extends from the anchor on shift, keeping the anchor', () => {
    const next = applyClick({ selected: new Set(['b']), anchor: 'b' }, IDS, 'd', { shift: true })

    expect([...next.selected]).toEqual(['b', 'c', 'd'])
    expect(next.anchor).toBe('b')
  })

  it('adds without clearing on meta', () => {
    const next = applyClick({ selected: new Set(['a']), anchor: 'a' }, IDS, 'd', { meta: true })

    expect([...next.selected]).toEqual(['a', 'd'])
  })

  it('falls back to a plain click when shift has no anchor', () => {
    const next = applyClick({ selected: new Set(), anchor: null }, IDS, 'c', { shift: true })

    expect([...next.selected]).toEqual(['c'])
  })
})
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `pnpm --filter studio test gallerySelection`
Expected: FAIL, `Failed to resolve import './gallerySelection'`

- [ ] **Step 3: Implementierung schreiben**

`apps/studio/resources/js/admin/lib/gallerySelection.ts`

```ts
export interface SelectionState {
  selected: Set<string>
  anchor: string | null
}

export interface Modifiers {
  shift?: boolean
  meta?: boolean
}

export function toggle(selected: Set<string>, id: string): Set<string> {
  const next = new Set(selected)

  if (!next.delete(id)) {
    next.add(id)
  }

  return next
}

export function range(ids: string[], anchor: string, target: string): string[] {
  const from = ids.indexOf(anchor)
  const to = ids.indexOf(target)

  return ids.slice(Math.min(from, to), Math.max(from, to) + 1)
}

export function applyClick(state: SelectionState, ids: string[], id: string, modifiers: Modifiers): SelectionState {
  // Der Anker bleibt stehen, solange mit Shift gezogen wird — sonst wanderte
  // die Strecke mit jedem Klick und man koennte sie nie verkuerzen.
  if (modifiers.shift && state.anchor !== null) {
    return { selected: new Set(range(ids, state.anchor, id)), anchor: state.anchor }
  }

  if (modifiers.meta) {
    return { selected: toggle(state.selected, id), anchor: id }
  }

  return { selected: new Set([id]), anchor: id }
}
```

- [ ] **Step 4: Test laufen lassen**

Run: `pnpm --filter studio test gallerySelection`
Expected: PASS, 6 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/lib/gallerySelection.ts apps/studio/resources/js/admin/lib/gallerySelection.test.ts
git commit -m "feat(gallery): Auswahllogik als reine Funktionen"
```

---

## Task 13: Rasterdichte je Nutzer und Set

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000800_create_gallery_grid_prefs_table.php`
- Create: `apps/studio/app/Gallery/Models/GalleryGridPref.php`
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryRecordController.php`
- Modify: `apps/studio/routes/admin.php`
- Test: `apps/studio/tests/Feature/Gallery/GalleryGridPrefTest.php`

**Interfaces:**
- Produces: `PATCH /galerien/{gallery}/sets/{gallerySet}/dichte` mit `density` aus `small|medium|large`. Record-Prop `sets[].density`.

- [ ] **Step 1: Failing test schreiben**

```php
<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('remembers the density per user and set', function () {
    $staff = User::factory()->withMfa()->create();
    $other = User::factory()->withMfa()->create();
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();

    $this->actingAs($staff, 'staff')
        ->patch("/galerien/{$gallery->public_id}/sets/{$set->public_id}/dichte", ['density' => 'large'])
        ->assertRedirect();

    expect(collect($this->actingAs($staff, 'staff')
        ->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['sets'])
        ->firstWhere('id', $set->public_id)['density'])->toBe('large')
        ->and(collect($this->actingAs($other, 'staff')
            ->get("/galerien/{$gallery->public_id}")->viewData('page')['props']['sets'])
            ->firstWhere('id', $set->public_id)['density'])->toBe('medium');
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryGridPrefTest.php`
Expected: FAIL, 404

- [ ] **Step 3: Migration und Modell**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dieselbe Form, die recent_views (C-03, #237) fuer dieselbe Art
     * Vorliebe schon verwendet. Eine json-Spalte am Nutzer saemmelte
     * Waisen, die niemand aufraeumt; hier nimmt der Fremdschluessel sie mit.
     */
    public function up(): void
    {
        Schema::create('gallery_grid_prefs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_set_id')->constrained('gallery_sets')->cascadeOnDelete();

            $table->string('density')->default('medium');

            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id', 'gallery_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_grid_prefs');
    }
};
```

`apps/studio/app/Gallery/Models/GalleryGridPref.php`

```php
<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GalleryGridPref extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'gallery_set_id', 'density'];
}
```

- [ ] **Step 4: Route und Prop**

In `GallerySetController` ergänzen:

```php
    public function density(Request $request, Gallery $gallery, GallerySet $gallerySet): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'density' => ['required', Rule::in(['small', 'medium', 'large'])],
        ]);

        GalleryGridPref::updateOrCreate(
            ['user_id' => auth('staff')->id(), 'gallery_set_id' => $gallerySet->id],
            ['density' => $validated['density']],
        );

        return back();
    }
```

Route:

```php
        Route::patch('/galerien/{gallery}/sets/{gallerySet}/dichte', [GallerySetController::class, 'density'])
            ->name('galleries.sets.density');
```

In `GalleryRecordController::show()` die Vorlieben laden und in die `sets`-Props hängen:

```php
        $densities = GalleryGridPref::query()
            ->where('user_id', auth('staff')->id())
            ->whereIn('gallery_set_id', $sets->pluck('id'))
            ->pluck('density', 'gallery_set_id');
```

und im `map()` `'density' => $densities[$set->id] ?? 'medium',` ergänzen.

- [ ] **Step 5: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryGridPrefTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add apps/studio/database/migrations/2026_08_24_000800_create_gallery_grid_prefs_table.php apps/studio/app/Gallery apps/studio/routes/admin.php apps/studio/tests/Feature/Gallery/GalleryGridPrefTest.php
git commit -m "feat(gallery): Rasterdichte je Nutzer und Set"
```

---

## Task 14: Schiene und Record-Hülle

**Files:**
- Create: `apps/studio/resources/js/admin/components/gallery/GalleryRail.vue`
- Modify: `apps/studio/resources/js/admin/pages/Galleries/Record.vue`
- Modify: `apps/studio/resources/js/locales/de.json`

Der Bilder-Reiter bleibt in diesem Task der bestehende Block aus `Record.vue`; Task 15 ersetzt ihn durch `panes/Images.vue`.

**Interfaces:**
- Consumes: Props aus Task 6 und 13 (`sets`, `assets`, `gallery`, `figures`), Routen aus Task 8.
- Produces: `GalleryRail.vue` mit Props `{ coverSrc: string | null; tab: string; sets: SetRow[]; activeSetId: string | null }` und Events `update:tab`, `update:activeSetId`, `create`, `rename`, `remove`, `reorder`. `SetRow = { id: string; name: string; count: number; density: string }`.

- [ ] **Step 1: Schiene schreiben**

`apps/studio/resources/js/admin/components/gallery/GalleryRail.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue'

export interface SetRow {
  id: string
  name: string
  count: number
  density: string
}

const props = defineProps<{
  coverSrc: string | null
  tab: string
  sets: SetRow[]
  activeSetId: string | null
}>()

const emit = defineEmits<{
  'update:tab': [string]
  'update:activeSetId': [string | null]
  create: [string]
  rename: [{ id: string; name: string }]
  remove: [string]
}>()

const TABS = ['bilder', 'gestaltung', 'einstellungen', 'aktivitaet'] as const

const editing = ref<string | null>(null)
const draft = ref('')
const adding = ref(false)

function startRename(row: SetRow) {
  editing.value = row.id
  draft.value = row.name
}

function commitRename(row: SetRow) {
  if (draft.value !== '' && draft.value !== row.name) {
    emit('rename', { id: row.id, name: draft.value })
  }

  editing.value = null
}

function commitCreate() {
  if (draft.value !== '') {
    emit('create', draft.value)
  }

  adding.value = false
  draft.value = ''
}
</script>

<template>
  <div class="w-[212px] flex-none border-r border-border">
    <div class="aspect-4/3 bg-muted">
      <img v-if="coverSrc" class="h-full w-full object-cover" :src="`${coverSrc}?w=420&fmt=jpeg`" alt="" />
    </div>

    <div class="flex border-b border-border">
      <button
        v-for="name in TABS"
        :key="name"
        class="flex-1 cursor-pointer border-0 bg-transparent py-2.5 text-[13px]"
        :class="tab === name ? 'text-foreground shadow-[inset_0_-2px_0_var(--ink)]' : 'text-faint'"
        type="button"
        :title="$t(`galleryRecord.tab.${name}`)"
        @click="emit('update:tab', name)"
      >
        {{ $t(`galleryRecord.tabIcon.${name}`) }}
      </button>
    </div>

    <template v-if="tab === 'bilder' && sets.length > 1">
      <div class="flex items-center px-3 pt-3 pb-1.5">
        <span class="text-[9.5px] font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('galleryRecord.sets') }}</span>
        <button class="ml-auto cursor-pointer border-0 bg-transparent text-[11.5px] text-action" type="button" @click="adding = true">
          {{ $t('galleryRecord.addSet') }}
        </button>
      </div>

      <div
        v-for="row in sets"
        :key="row.id"
        class="mx-1.5 flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px]"
        :class="activeSetId === row.id && 'bg-sunk-2 font-semibold'"
        @click="emit('update:activeSetId', row.id)"
        @dblclick="startRename(row)"
      >
        <Input
          v-if="editing === row.id"
          v-model="draft"
          class="h-6 text-[12.5px]"
          type="text"
          autofocus
          @blur="commitRename(row)"
          @keyup.enter="commitRename(row)"
          @keyup.esc="editing = null"
        />
        <template v-else>
          {{ row.name }}
          <span class="ml-auto text-[11px] tabular-nums text-ink-2">{{ row.count }}</span>
        </template>
      </div>

      <div v-if="adding" class="mx-1.5 px-3 py-2">
        <Input v-model="draft" class="h-6 text-[12.5px]" type="text" autofocus @blur="commitCreate" @keyup.enter="commitCreate" />
      </div>
    </template>
  </div>
</template>
```

Import ergänzen: `import { Input } from '@/ui/input'`

- [ ] **Step 2: Record-Hülle umbauen**

`Record.vue` behält seinen `<script setup>`-Kopf, tauscht aber die Reiterleiste gegen die Schiene und lagert den Bilder-Reiter aus:

```vue
<template>
  <AdminLayout active="galerien">
    <div data-tokens>
      <PageHead :title="gallery.title ?? '—'" />

      <div class="flex">
        <GalleryRail
          v-model:tab="tab"
          v-model:active-set-id="activeSetId"
          :cover-src="gallery.coverSrc"
          :sets="sets"
          @create="createSet"
          @rename="renameSet"
          @remove="removeSet"
        />

        <div v-if="tab === 'bilder'" class="min-w-0 flex-1 pt-5 pr-8 pb-7">
          <!-- Der bestehende Raster- und Versionsblock aus Record.vue (heute
               Zeilen 227-253) wandert unveraendert hierher. Die alte
               Set-Spalte links davon (Zeilen 210-226) faellt weg, die
               Schiene ersetzt sie. Task 15 ersetzt diesen Block ganz. -->
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

mit

```ts
const tab = ref<'bilder' | 'gestaltung' | 'einstellungen' | 'aktivitaet'>('bilder')
const activeSetId = ref<string | null>(props.sets[0]?.id ?? null)

function createSet(name: string) {
  router.post(`/galerien/${props.gallery.id}/sets`, { name }, { preserveScroll: true })
}

function renameSet({ id, name }: { id: string; name: string }) {
  router.patch(`/galerien/${props.gallery.id}/sets/${id}`, { name }, { preserveScroll: true })
}

function removeSet(id: string) {
  router.delete(`/galerien/${props.gallery.id}/sets/${id}`, { preserveScroll: true })
}
```

Die Reiter `gestaltung`, `einstellungen` und `aktivitaet` bekommen ihre Panes in Etappe 3 bis 5; bis dahin bleiben die bestehenden Blöcke aus `Record.vue` unter dem jeweiligen `v-else-if` stehen.

- [ ] **Step 3: Locale-Schlüssel ergänzen**

Unter `galleryRecord` in `de.json`:

```json
"sets": "Sets",
"tab": { "bilder": "Bilder", "gestaltung": "Gestaltung", "einstellungen": "Einstellungen", "aktivitaet": "Aktivität" },
"tabIcon": { "bilder": "▦", "gestaltung": "✎", "einstellungen": "⚙", "aktivitaet": "◷" }
```

- [ ] **Step 4: Prüfen**

Run: `pnpm lint`
Expected: keine Fehler

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest tests/Feature/Gallery/GalleryRecordTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/gallery/GalleryRail.vue apps/studio/resources/js/admin/pages/Galleries/Record.vue apps/studio/resources/js/locales/de.json
git commit -m "feat(gallery): Record bekommt eine Schiene"
```

---

## Task 15: Bilder-Reiter mit Auswahl, Dichte und Leichtansicht

**Files:**
- Create: `apps/studio/resources/js/admin/pages/Galleries/panes/Images.vue`
- Create: `apps/studio/resources/js/admin/components/gallery/AssetLightbox.vue`
- Modify: `apps/studio/resources/js/admin/pages/Galleries/Record.vue` (Bilder-Block durch `<Images>` ersetzen)

**Interfaces:**
- Consumes: `applyClick` aus Task 12, Routen aus Task 9 und 13, `useVirtualList` aus `@vueuse/core`.
- Produces: keine — Endverbraucher.

- [ ] **Step 1: Leichtansicht schreiben**

`apps/studio/resources/js/admin/components/gallery/AssetLightbox.vue`

```vue
<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

const props = defineProps<{ src: string; filename: string }>()
const emit = defineEmits<{ close: []; prev: []; next: [] }>()

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('close')
  if (event.key === 'ArrowLeft') emit('prev')
  if (event.key === 'ArrowRight') emit('next')
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col bg-[rgba(15,15,15,0.92)]" @click.self="emit('close')">
    <div class="flex items-center gap-4 px-6 py-4 text-[12.5px] text-paper">
      {{ filename }}
      <button class="ml-auto cursor-pointer border-0 bg-transparent text-paper" type="button" @click="emit('close')">✕</button>
    </div>
    <img class="min-h-0 flex-1 object-contain px-6 pb-6" :src="`${props.src}?w=2000&fmt=jpeg`" alt="" />
  </div>
</template>
```

- [ ] **Step 2: Bilder-Reiter schreiben**

`apps/studio/resources/js/admin/pages/Galleries/panes/Images.vue`

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useVirtualList } from '@vueuse/core'
import { applyClick, type SelectionState } from '@/admin/lib/gallerySelection'
import AssetLightbox from '@/admin/components/gallery/AssetLightbox.vue'
import { NativeSelect } from '@/ui/native-select'

interface AssetRow {
  id: string
  key: string
  setId: string
  filename: string
  src: string | null
  isCover: boolean
}

const props = defineProps<{
  gallery: { id: string }
  sets: { id: string; name: string; count: number; density: string }[]
  assets: AssetRow[]
  activeSetId: string | null
}>()

const COLUMNS: Record<string, number> = { small: 10, medium: 7, large: 4 }

const set = computed(() => props.sets.find((row) => row.id === props.activeSetId) ?? props.sets[0])
const columns = computed(() => COLUMNS[set.value?.density ?? 'medium'])
const shown = computed(() => props.assets.filter((asset) => asset.setId === set.value?.id))

// useVirtualList arbeitet auf Zeilen, nicht auf Kacheln — 850 Bilder sind 122
// Zeilen bei mittlerer Dichte, und nur die sichtbaren gehen ins DOM.
const rows = computed(() => {
  const chunks: AssetRow[][] = []

  for (let index = 0; index < shown.value.length; index += columns.value) {
    chunks.push(shown.value.slice(index, index + columns.value))
  }

  return chunks
})

const { list, containerProps, wrapperProps } = useVirtualList(rows, { itemHeight: 132 })

const selection = ref<SelectionState>({ selected: new Set(), anchor: null })
const lightbox = ref<AssetRow | null>(null)

function click(asset: AssetRow, event: MouseEvent) {
  selection.value = applyClick(
    selection.value,
    shown.value.map((row) => row.id),
    asset.id,
    { shift: event.shiftKey, meta: event.metaKey || event.ctrlKey },
  )
}

function moveTo(setId: string) {
  router.post(
    `/galerien/${props.gallery.id}/bilder/verschieben`,
    { asset_ids: [...selection.value.selected], gallery_set_id: setId },
    { preserveScroll: true, onSuccess: () => (selection.value = { selected: new Set(), anchor: null }) },
  )
}

function remove() {
  router.delete(`/galerien/${props.gallery.id}/bilder`, {
    data: { asset_ids: [...selection.value.selected] },
    preserveScroll: true,
    onSuccess: () => (selection.value = { selected: new Set(), anchor: null }),
  })
}

function setDensity(density: string) {
  router.patch(`/galerien/${props.gallery.id}/sets/${set.value.id}/dichte`, { density }, { preserveScroll: true })
}

function step(by: number) {
  const index = shown.value.findIndex((row) => row.id === lightbox.value?.id)

  lightbox.value = shown.value[index + by] ?? lightbox.value
}
</script>

<template>
  <div class="relative px-6 pt-5 pb-7">
    <div class="mb-3 flex items-center gap-3">
      <h2 class="m-0 text-[17px] leading-[1.2] font-semibold tracking-[-0.022em]">{{ set?.name }}</h2>
      <span class="text-[12px] text-ink-2">{{ shown.length }}</span>
      <div class="ml-auto flex gap-3 text-[11.5px] text-ink-2">
        <button
          v-for="option in ['small', 'medium', 'large']"
          :key="option"
          class="cursor-pointer border-0 bg-transparent text-[11.5px]"
          :class="set?.density === option ? 'font-semibold text-foreground' : 'text-ink-2'"
          type="button"
          @click="setDensity(option)"
        >
          {{ $t(`galleryRecord.density.${option}`) }}
        </button>
      </div>
    </div>

    <div v-bind="containerProps" class="h-[calc(100vh-260px)]">
      <div v-bind="wrapperProps">
        <div
          v-for="row in list"
          :key="row.index"
          class="mb-1.5 grid gap-1.5"
          :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
        >
          <figure
            v-for="asset in row.data"
            :key="asset.id"
            class="relative m-0 cursor-pointer"
            @click="click(asset, $event)"
            @dblclick="lightbox = asset"
          >
            <img
              v-if="asset.src"
              class="block aspect-square w-full rounded-md object-cover"
              :class="selection.selected.has(asset.id) && 'outline-2 -outline-offset-2 outline-foreground'"
              :src="`${asset.src}?w=400&fmt=jpeg`"
              alt=""
              loading="lazy"
            />
            <span v-if="asset.isCover" class="absolute bottom-1.5 left-1.5 rounded bg-[rgba(25,25,25,0.7)] px-1.5 py-0.5 text-[8.5px] text-paper">
              {{ $t('galleryRecord.cover') }}
            </span>
          </figure>
        </div>
      </div>
    </div>

    <div
      v-if="selection.selected.size > 0"
      class="fixed bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-4 rounded-full bg-foreground px-5 py-2.5 text-[12.5px] text-paper"
    >
      <b>{{ $t('galleryRecord.selected', selection.selected.size) }}</b>
      <NativeSelect class="h-7 border-0 bg-transparent text-paper" @change="moveTo(($event.target as HTMLSelectElement).value)">
        <option value="">{{ $t('galleryRecord.moveTo') }}</option>
        <option v-for="row in sets" :key="row.id" :value="row.id">{{ row.name }}</option>
      </NativeSelect>
      <button class="cursor-pointer border-0 bg-transparent text-paper" type="button" @click="remove">
        {{ $t('common.delete') }}
      </button>
    </div>

    <AssetLightbox
      v-if="lightbox?.src"
      :src="lightbox.src"
      :filename="lightbox.filename"
      @close="lightbox = null"
      @prev="step(-1)"
      @next="step(1)"
    />
  </div>
</template>
```

- [ ] **Step 3: Locale-Schlüssel ergänzen**

```json
"density": { "small": "Klein", "medium": "Mittel", "large": "Groß" },
"selected": "{n} ausgewählt | {n} ausgewählt",
"cover": "Titelbild"
```

- [ ] **Step 4: Prüfen**

Run: `pnpm lint`
Expected: keine Fehler

Run: `pnpm --filter studio test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/pages/Galleries/panes/Images.vue apps/studio/resources/js/admin/components/gallery/AssetLightbox.vue apps/studio/resources/js/locales/de.json
git commit -m "feat(gallery): Bilder auswaehlen, verschieben, ansehen"
```

---

## Task 16: Upload im Reiter, Ingest-Seite entfällt

**Files:**
- Create: `apps/studio/resources/js/admin/components/gallery/DropZone.vue`
- Create: `apps/studio/resources/js/admin/components/gallery/UploadTray.vue`
- Create: `apps/studio/resources/js/admin/components/gallery/DuplicateDialog.vue`
- Modify: `apps/studio/resources/js/admin/pages/Galleries/panes/Images.vue`
- Modify: `apps/studio/app/Gallery/Http/Controllers/GalleryIndexController.php:107`
- Delete: `apps/studio/resources/js/admin/pages/Galleries/Ingest.vue`, `apps/studio/app/Media/Http/Controllers/MediaIngestController.php`, `apps/studio/app/Media/Data/MediaIngestTargetData.php`
- Modify: `apps/studio/routes/admin.php:463`
- Test: `apps/studio/tests/Feature/Gallery/GalleryIngestTargetTest.php` (erweitert)

**Interfaces:**
- Consumes: `uploadFile` (Task 4), Vorabprüfung (Task 10).
- Produces: keine.

- [ ] **Step 1: Failing test schreiben**

Anhängen an `GalleryIngestTargetTest.php`

```php
it('has no ingest page of its own any more', function () {
    $gallery = Gallery::factory()->create();

    $this->get("/galerien/{$gallery->public_id}/medien")->assertNotFound();
});

it('lands a fresh gallery on its own record', function () {
    $response = $this->post('/galerien', ['title' => 'Fotobox', 'is_external' => false]);

    $gallery = Gallery::latest('id')->first();

    $response->assertRedirect("/galerien/{$gallery->public_id}");
});
```

- [ ] **Step 2: Test laufen lassen, Fehlschlag sehen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest --filter='has no ingest page'`
Expected: FAIL, 200 statt 404

- [ ] **Step 3: Route, Controller und Seite entfernen**

Route `/galerien/{gallery}/medien` aus `routes/admin.php` streichen samt Import von `MediaIngestController`. Die drei Dateien löschen. In `GalleryIndexController::store()` das Ziel des Redirects auf `/galerien/{$gallery->public_id}` setzen — es gibt keine getrennte Ingest-Seite mehr.

- [ ] **Step 4: Ablagefläche, Tray und Dialog schreiben**

`DropZone.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{ files: [File[]] }>()
const over = ref(false)

function onDrop(event: DragEvent) {
  over.value = false
  emit('files', Array.from(event.dataTransfer?.files ?? []))
}
</script>

<template>
  <label
    class="block cursor-pointer rounded-lg border border-dashed border-border px-6 py-9 text-center text-[12.5px] text-ink-2"
    :class="over && 'border-action bg-accent-bg'"
    @dragover.prevent="over = true"
    @dragleave="over = false"
    @drop.prevent="onDrop"
  >
    <b class="block text-[14px] font-semibold text-foreground">{{ $t('media.dropHere') }}</b>
    {{ $t('media.pickFiles') }}
    <input type="file" multiple class="hidden" @change="emit('files', Array.from(($event.target as HTMLInputElement).files ?? []))" />
  </label>
</template>
```

`UploadTray.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue'

export interface TrayRow {
  key: string
  name: string
  size: number
  status: 'queued' | 'uploading' | 'done' | 'error'
  percent: number
}

const props = defineProps<{ rows: TrayRow[] }>()

const done = computed(() => props.rows.filter((row) => row.status === 'done').length)
const percent = computed(() => (props.rows.length === 0 ? 0 : Math.round((done.value / props.rows.length) * 100)))
</script>

<template>
  <div v-if="rows.length > 0" class="fixed right-5 bottom-5 w-[260px] overflow-hidden rounded-lg border border-border bg-paper shadow-lg">
    <div class="border-b border-border px-3 py-2.5">
      <b class="text-[12px] font-semibold">{{ $t('media.filesLabel') }}</b>
      <span class="mt-0.5 block text-[11px] tabular-nums text-ink-2">{{ done }} / {{ rows.length }} · {{ percent }} %</span>
    </div>
    <span class="block h-[3px] bg-muted"><i class="block h-full bg-foreground" :style="{ width: `${percent}%` }" /></span>
    <div v-for="row in rows.slice(0, 4)" :key="row.key" class="flex items-center gap-2 border-t border-border px-3 py-1.5 text-[11px]">
      <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
      <span class="tabular-nums text-ink-2">{{ row.status === 'uploading' ? `${row.percent} %` : $t(`media.${row.status}`) }}</span>
    </div>
  </div>
</template>
```

`DuplicateDialog.vue`

```vue
<script setup lang="ts">
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'

defineProps<{ open: boolean; duplicates: { filename: string; size: number }[] }>()
const emit = defineEmits<{ skip: []; replace: [] }>()

function megabytes(bytes: number) {
  return `${(bytes / 1_000_000).toFixed(1)} MB`
}
</script>

<template>
  <Dialog :open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('media.duplicatesFound', duplicates.length) }}</DialogTitle>
      </DialogHeader>
      <DialogBody>
        <div v-for="row in duplicates" :key="row.filename" class="flex gap-3 py-1 text-[12.5px]">
          <span class="min-w-0 flex-1 truncate">{{ row.filename }}</span>
          <span class="tabular-nums text-ink-2">{{ megabytes(row.size) }}</span>
        </div>
      </DialogBody>
      <DialogFooter>
        <Button type="button" @click="emit('replace')">{{ $t('media.replace') }}</Button>
        <Button variant="ghost" type="button" @click="emit('skip')">{{ $t('media.skipDuplicates') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
```

- [ ] **Step 5: Ablauf im Bilder-Reiter verdrahten**

In `Images.vue` ergänzen:

```ts
import DropZone from '@/admin/components/gallery/DropZone.vue'
import DuplicateDialog from '@/admin/components/gallery/DuplicateDialog.vue'
import UploadTray, { type TrayRow } from '@/admin/components/gallery/UploadTray.vue'
import { uploadFile } from '@/admin/lib/multipartUpload'

const tray = ref<TrayRow[]>([])
const pending = ref<File[]>([])
const duplicates = ref<{ filename: string; size: number; assetId: string }[]>([])

async function picked(files: File[]) {
  pending.value = files

  const response = await fetch(`/galerien/${props.gallery.id}/uploads/pruefen`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ files: files.map((file) => ({ filename: file.name, size: file.size })) }),
  })

  duplicates.value = (await response.json()).duplicates

  if (duplicates.value.length === 0) {
    await run(files, new Map())
  }
}

async function run(files: File[], replacements: Map<string, string>) {
  tray.value = files.map((file) => ({
    key: `${file.name}:${file.size}`,
    name: file.name,
    size: file.size,
    status: 'queued',
    percent: 0,
  }))

  for (const file of files) {
    const row = tray.value.find((entry) => entry.key === `${file.name}:${file.size}`)!
    row.status = 'uploading'

    try {
      await uploadFile(
        `/galerien/${props.gallery.id}/uploads`,
        file,
        ({ partsDone, partsTotal }) => (row.percent = partsTotal === 0 ? 0 : Math.round((partsDone / partsTotal) * 100)),
        set.value.id,
        replacements.get(`${file.name}:${file.size}`),
      )
      row.status = 'done'
    } catch {
      row.status = 'error'
    }
  }

  router.reload({ only: ['assets', 'sets', 'figures'] })
}

function skipDuplicates() {
  const names = new Set(duplicates.value.map((row) => `${row.filename}:${row.size}`))

  duplicates.value = []
  void run(pending.value.filter((file) => !names.has(`${file.name}:${file.size}`)), new Map())
}

function replaceDuplicates() {
  const map = new Map(duplicates.value.map((row) => [`${row.filename}:${row.size}`, row.assetId]))

  duplicates.value = []
  void run(pending.value, map)
}
```

`uploadFile` bekommt in `multipartUpload.ts` den fünften, optionalen Parameter `replacesAssetId?: string`, der als `replaces_asset_id` in den Startaufruf geht — die Signatur lautet danach `uploadFile(startUrl, file, onProgress, gallerySetId, replacesAssetId?)`.

Im Template unter das Raster:

```vue
    <DropZone class="mt-3" @files="picked" />
    <UploadTray :rows="tray" />
    <DuplicateDialog :open="duplicates.length > 0" :duplicates="duplicates" @skip="skipDuplicates" @replace="replaceDuplicates" />
```

Und die Warnung beim Wegnavigieren:

```ts
import { onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const transferring = computed(() => tray.value.some((row) => row.status === 'uploading' || row.status === 'queued'))

function guard(event: BeforeUnloadEvent) {
  event.preventDefault()
}

watch(transferring, (on) => (on ? window.addEventListener('beforeunload', guard) : window.removeEventListener('beforeunload', guard)))

// beforeunload faengt nur den harten Weggang. Inertia wechselt die Seite
// ohne Neuladen, und der laufende Transfer stirbt mit der Komponente.
const stopInertiaGuard = router.on('before', () =>
  transferring.value ? window.confirm(t('media.leaveWhileUploading')) : true,
)

onUnmounted(() => {
  window.removeEventListener('beforeunload', guard)
  stopInertiaGuard()
})
```

- [ ] **Step 6: Locale-Schlüssel ergänzen**

Unter `media` in `de.json`:

```json
"dropHere": "Bilder hierher ziehen",
"duplicatesFound": "{n} Datei liegt schon hier | {n} Dateien liegen schon hier",
"replace": "Ersetzen",
"skipDuplicates": "Überspringen",
"leaveWhileUploading": "Der Upload läuft noch. Seite verlassen?"
```

Nicht mehr benutzte `media`-Schlüssel aus der alten Ingest-Seite entfernen: `backToProject`, `target`, `resumeHint`, `transferring`, `of`.

- [ ] **Step 7: Tests laufen lassen**

Run: `docker compose run --rm -e APP_SURFACE=admin php ./vendor/bin/pest`
Expected: PASS, gesamte Suite

Run: `pnpm lint && pnpm --filter studio test`
Expected: PASS

- [ ] **Step 8: E2E-Baselines neu schießen**

Run: `node apps/studio/tests/e2e/shots/gallery.mjs <galleryPublicId>`
Erwartung: neue Aufnahmen unter `docs/screenshots`; die Galerie-Bilder unterscheiden sich, weil Schiene und Bilder-Reiter neu sind. Alte Aufnahmen der Ingest-Seite löschen.

- [ ] **Step 9: Commit**

```bash
git add apps/studio/resources/js apps/studio/app apps/studio/routes/admin.php apps/studio/tests docs/screenshots
git commit -m "feat(gallery): Upload lebt im Bilder-Reiter"
```

---

## Selbstprüfung gegen die Spec

**Abgedeckt in dieser Etappe:** Entscheidungen 2 (Task 1–7), 6 (Task 5), 10 (Task 3), 11 (Task 7), 12 (Task 2), 13 (Task 1, 7), 14 (Task 8), 15 (Task 9), 16 (Task 5), 17 (Task 13), 19 (Task 10), 20 (Task 10), 21 (Task 11), 22 (Task 11), 23 (Task 11), 28 (Task 16), 30 (Task 14), 31 (Task 14), 32 (Task 15), 33 (Task 15), 37 (Task 11), 38 (Task 16).

**Bewusst in spätere Etappen verschoben:** 1 (Record-Hülle steht, die drei übrigen Panes folgen), 3, 4, 8, 9, 18, 24, 25, 26, 27, 29, 34, 35, 36 — sie gehören zu Übersicht, Anlegen, Gestaltung, Einstellungen und Aktivität.

**Nicht abgedeckt und bewusst offen:** Entscheidung 5 (zwölf Cover) ist Etappe 3 und der größte Einzelposten; ohne sie liefert `apps/gallery` weiter ihr festes Layout, liest aber ab Task 5 bereits `design` und `cover_key` aus der Version, sobald die Read-API sie ausgibt.

---

Plan complete and saved to `docs/superpowers/plans/2026-08-23-galerie-fundament.md`. Two execution options:

**1. Subagent-Driven (recommended)** — ein frischer Subagent pro Task, Review dazwischen, schnelle Schleife

**2. Inline Execution** — Tasks in dieser Sitzung, Stapel mit Haltepunkten

Which approach?
