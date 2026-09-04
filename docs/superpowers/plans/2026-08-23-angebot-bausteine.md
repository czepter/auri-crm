# S2 · Bausteine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unter der letzten Position steht ein Suchfeld, das Pakete, gemerkte Positionen und eine frei getippte Zeile in dieselbe Liste bringt; ein Häkchen an der Zeile füllt die Bibliothek.

**Architecture:** Ein neues Modell `CatalogItem` ohne jede Beziehung — nichts zeigt hin, nichts zeigt weg, gefüllt über ein abgeleitetes Häkchen statt über eine Spalte. Filterung und Gruppierung liegen als reine Funktionen in `lib/insert.ts` und werden von Vitest geprüft; die Combobox ist ein lokaler Wrapper über `reka-ui`, weil die konfigurierte Registry keinen führt. Ein Paket einzufügen bleibt Client-Arbeit: `QuoteController::update()` ersetzt die Positionen ohnehin vollständig.

**Tech Stack:** Laravel 12 · Inertia · Vue 3 `<script setup>` · reka-ui 2.10 · Tailwind · Pest · Vitest

**Spec:** [docs/superpowers/specs/2026-08-23-angebot-bausteine-design.md](../specs/2026-08-23-angebot-bausteine-design.md)

**Setzt voraus:** S1 (`docs/superpowers/plans/2026-08-23-angebot-tabelleneditor.md`) ist fertig. Ohne `QuoteTable.vue` gibt es keine Zeile, an die ein Häkchen käme, und ohne den Autosave keinen Schreibweg für das Einfügen.

**Issue:** #262

## Global Constraints

- Formularelemente kommen aus `resources/js/ui`. Kein rohes `<input>`, `<select>` oder `<button>`.
- Deutsche Beschriftungen kurz und nativ. Kein Emoji, kein Ausrufezeichen, keine Beruhigungssätze. Vor dem Einchecken die drei Nachbarn in `de.json` lesen.
- Kommentare nur für das *Warum*.
- Backend-Tests: `-e APP_SURFACE=admin` am Container.
- Geld sind Minor Units (`int`). `unit_price === null` heißt „Enthaltene Leistung", nicht null Euro.
- Nichts hält eine lebende Verbindung von einem Angebot zurück auf ein Paket oder einen Katalogeintrag.
- Vitest läuft mit `environment: 'node'` — es lässt sich nichts mounten. Prüfbare Logik gehört nach `lib/`.
- Arbeitsverzeichnis für alle Befehle: `apps/studio`.

---

### Task 1: `CatalogItem` — Tabelle, Modell, Data

**Files:**
- Create: `apps/studio/database/migrations/2026_08_24_000900_create_catalog_items_table.php`
- Create: `apps/studio/app/Catalog/Models/CatalogItem.php`
- Create: `apps/studio/app/Catalog/Data/CatalogItemData.php`
- Create: `apps/studio/database/factories/Catalog/CatalogItemFactory.php`
- Test: `apps/studio/tests/Feature/Catalog/CatalogItemTest.php`

**Interfaces:**
- Produces: `App\Catalog\Models\CatalogItem` mit `$fillable = ['title', 'description', 'unit_price']`, `App\Catalog\Data\CatalogItemData` mit `#[TypeScript]` und den Feldern `id`, `title`, `description`, `unitPrice`. Task 2 und Task 3 verwenden beide.

- [ ] **Step 1: Write the failing test**

`apps/studio/tests/Feature/Catalog/CatalogItemTest.php`:

```php
<?php

use App\Catalog\Models\CatalogItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a library line with a public id and no relations', function () {
    $item = CatalogItem::create(['title' => 'Zweitfotograf', 'description' => null, 'unit_price' => 49000]);

    expect($item->public_id)->not->toBeEmpty()
        ->and($item->tenant_id)->not->toBeNull()
        ->and($item->unit_price)->toBe(49000);
});

it('keeps a line without a price as an included service rather than as zero', function () {
    $item = CatalogItem::create(['title' => 'Anfahrt', 'unit_price' => null]);

    expect($item->unit_price)->toBeNull();
});

it('refuses a second line with the same title', function () {
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    expect(fn () => CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 52000]))
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/CatalogItemTest.php`
Expected: FAIL — `Class "App\Catalog\Models\CatalogItem" not found`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/database/migrations/2026_08_24_000900_create_catalog_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();
            $table->string('title');
            $table->text('description')->nullable();

            // Minor units, dieselbe Bedeutung wie in package_items: null ist
            // "Enthaltene Leistung", nicht null Euro.
            $table->unsignedInteger('unit_price')->nullable();
            $table->timestampsTz();

            // Der Index ist die Regel selbst: das Häkchen an einer Zeile
            // behauptet "diesen Titel gibt es in der Bibliothek", und zwei
            // Zeilen mit demselben Titel sind dieselbe Behauptung.
            $table->unique(['tenant_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
```

`apps/studio/app/Catalog/Models/CatalogItem.php`:

```php
<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Catalog\CatalogItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eine gemerkte Angebotszeile. Sie gehört zu nichts und steht in keiner
 * Ordnung — kein Fremdschlüssel zeigt hierher und keiner von hier weg, aus
 * demselben Grund, aus dem Package::copyItems() eine getrennte Kopie
 * liefert: was einmal in einem Angebot steht, darf sich hier nicht mehr
 * bewegen.
 */
class CatalogItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'description', 'unit_price'];

    protected static function newFactory(): Factory
    {
        return CatalogItemFactory::new();
    }
}
```

`apps/studio/app/Catalog/Data/CatalogItemData.php`:

```php
<?php

namespace App\Catalog\Data;

use App\Catalog\Models\CatalogItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CatalogItemData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?int $unitPrice,
    ) {}

    public static function fromModel(CatalogItem $item): self
    {
        return new self(
            id: $item->public_id,
            title: $item->title,
            description: $item->description,
            unitPrice: $item->unit_price,
        );
    }
}
```

`apps/studio/database/factories/Catalog/CatalogItemFactory.php` — nach dem Muster von `PackageFactory`:

```php
<?php

namespace Database\Factories\Catalog;

use App\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'description' => null,
            'unit_price' => fake()->numberBetween(5000, 200000),
        ];
    }

    public function included(): static
    {
        return $this->state(['unit_price' => null]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Catalog/CatalogItemTest.php`
Expected: PASS, 3 Tests

- [ ] **Step 5: TypeScript-Typen erzeugen und committen**

Run: `docker compose exec api php artisan typescript:transform`

```bash
git add apps/studio/database/migrations/2026_08_24_000900_create_catalog_items_table.php apps/studio/app/Catalog/Models/CatalogItem.php apps/studio/app/Catalog/Data/CatalogItemData.php apps/studio/database/factories/Catalog/CatalogItemFactory.php apps/studio/tests/Feature/Catalog/CatalogItemTest.php apps/studio/resources/js/types
git commit -m "feat(catalog): Positionsbibliothek als eigenes Modell"
```

---

### Task 2: Merken im Autosave

Das Häkchen ist abgeleitet: angehakt heißt, es gibt einen `CatalogItem` mit diesem Titel. Geschrieben wird es im selben `PATCH`, der die Positionen speichert.

**Files:**
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTest.php`

**Interfaces:**
- Consumes: `CatalogItem` aus Task 1
- Produces: Validierungsregel `items.*.remember` (`boolean`); private Methode `syncCatalog(array $items): void` in `QuoteController`

- [ ] **Step 1: Write the failing test**

An `apps/studio/tests/Feature/Quotes/QuoteTest.php` anhängen. `CatalogItem`, `Package` und `StaffRole` im Kopf importieren, falls sie fehlen.

```php
it('remembers a flagged line, and remembers it only once across quotes', function () {
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();
    $line = ['title' => 'Zweitfotograf', 'description' => 'Zweite Kamera', 'quantity' => 1, 'unit_price' => 49000, 'remember' => true];

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$first->public_id}", ['items' => [$line]]);
    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$second->public_id}", ['items' => [$line]]);

    $item = CatalogItem::sole();

    expect($item->title)->toBe('Zweitfotograf')
        ->and($item->description)->toBe('Zweite Kamera')
        ->and($item->unit_price)->toBe(49000);
});

it('updates the remembered price and description, and never the quantity', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'description' => null, 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'items' => [['title' => 'Zweitfotograf', 'description' => 'Ganztags', 'quantity' => 3, 'unit_price' => 52000, 'remember' => true]],
    ]);

    $item = CatalogItem::sole();

    expect($item->unit_price)->toBe(52000)
        ->and($item->description)->toBe('Ganztags')
        ->and($item->getAttributes())->not->toHaveKey('quantity');
});

it('forgets a line whose flag is taken away', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'items' => [['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 1, 'unit_price' => 49000, 'remember' => false]],
    ]);

    expect(CatalogItem::count())->toBe(0);
});

it('leaves the library alone when no line carries the flag at all', function () {
    $quote = Quote::factory()->create();
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $this->actingAs(quoteOwner(), 'staff')->patch("/angebote/{$quote->public_id}", [
        'items' => [['title' => 'Reportage', 'description' => null, 'quantity' => 1, 'unit_price' => 189000]],
    ]);

    expect(CatalogItem::count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="remembers a flagged line"`
Expected: FAIL — `CatalogItem::sole()` findet nichts

- [ ] **Step 3: Write minimal implementation**

In `QuoteController::update()` die Validierung ergänzen:

```php
'items.*.remember' => ['boolean'],
```

Nach `$quote->recalculateTotal();` einfügen:

```php
$this->syncCatalog($validated['items'] ?? []);
```

Und die Methode ans Ende der Klasse, mit `use App\Catalog\Models\CatalogItem;` im Kopf:

```php
/**
 * Das Häkchen ist keine Spalte auf quote_items, sondern die Frage "gibt es
 * einen Katalogeintrag mit diesem Titel". Eine Zeile ohne den Schlüssel
 * stellt die Frage nicht und lässt die Bibliothek unberührt.
 *
 * @param  list<array<string, mixed>>  $items
 */
private function syncCatalog(array $items): void
{
    foreach ($items as $item) {
        if (! array_key_exists('remember', $item)) {
            continue;
        }

        if ($item['remember']) {
            CatalogItem::updateOrCreate(
                ['title' => $item['title']],
                ['description' => $item['description'] ?? null, 'unit_price' => $item['unit_price'] ?? null],
            );

            continue;
        }

        CatalogItem::where('title', $item['title'])->delete();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Quotes/Http/Controllers/QuoteController.php apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(quotes): Position merken beim Speichern"
```

---

### Task 3: Berechtigung für den Katalog

Der Autosave läuft unter `manageQuotes`. Die Bibliothek ist Katalog, dafür steht `Capability::ManagePackages` im Vokabular — heute hält jede Rolle beides, aber P6/I-01 gibt API-Token gegen einzelne Fähigkeiten aus.

**Files:**
- Modify: `apps/studio/app/Quotes/Http/Controllers/QuoteController.php`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTest.php`

**Interfaces:**
- Consumes: `syncCatalog()` aus Task 2

- [ ] **Step 1: Write the failing test**

Vorher in `app/Enums/StaffRole.php` nachsehen, welche Rolle `Capability::ManagePackages` **nicht** hält. Hält sie heute jede, wird der Test mit einer temporär entzogenen Fähigkeit unmöglich — dann stattdessen `Gate::define`-freies Vorgehen: den Test über `Gate::denyAll()`-Ersatz aufziehen, indem die Policy mit `Gate::policy()` nicht überschrieben, sondern der Nutzer über `actingAs` mit einer Rolle ohne die Fähigkeit gesetzt wird. Ergebnis dieser Prüfung im Test-Kommentar festhalten.

```php
it('refuses to write the library for an actor without the catalog capability', function () {
    $role = collect(StaffRole::cases())->first(fn (StaffRole $role) => ! $role->allows(Capability::ManagePackages));

    if ($role === null) {
        $this->markTestSkipped('Jede Rolle hält ManagePackages — die Trennung existiert nur für API-Token (P6/I-01).');
    }

    $quote = Quote::factory()->create();

    $this->actingAs(staffMember($role), 'staff')->patch("/angebote/{$quote->public_id}", [
        'items' => [['title' => 'Zweitfotograf', 'description' => null, 'quantity' => 1, 'unit_price' => 49000, 'remember' => true]],
    ])->assertForbidden();

    expect(CatalogItem::count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails or skips**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="without the catalog capability"`
Expected: FAIL mit 302/200 statt 403 — oder SKIPPED, falls jede Rolle die Fähigkeit hält. Ein Skip ist hier ein gültiges Ergebnis und wird nicht weggeräumt: er dokumentiert, dass die Trennung heute nur für Token existiert.

- [ ] **Step 3: Write minimal implementation**

In `QuoteController::update()` vor `syncCatalog()`, mit `use Illuminate\Support\Facades\Gate;` und `use App\Catalog\Models\Package;`:

```php
// Der Schlüssel kommt nur mit, wenn der Bildschirm das Häkchen gerendert
// hat. Kommt er trotzdem, ist das kein Bedienschritt, den man still
// verwerfen darf.
if (collect($validated['items'] ?? [])->contains(fn (array $item) => array_key_exists('remember', $item))
    && Gate::denies('create', Package::class)) {
    abort(403);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: PASS oder ein dokumentierter Skip

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Quotes/Http/Controllers/QuoteController.php apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(quotes): Katalogschreiben an ManagePackages binden"
```

---

### Task 4: Die Props für die Suchzeile

S1 hat die `packages`-Prop entfernt, weil sie ohne Dialog nichts rendert. Hier kommt sie zurück — als vollständige Data, nicht als Namensliste.

**Files:**
- Modify: `apps/studio/app/Crm/Http/Controllers/ProjectController.php:254-267`
- Test: `apps/studio/tests/Feature/Quotes/QuoteTest.php`

**Interfaces:**
- Produces: Props auf `Projects/Quotes` — `packages: PackageData[]`, `catalogItems: CatalogItemData[]`, `canManageCatalog: bool`

- [ ] **Step 1: Write the failing test**

```php
it('sends active packages with their items, the library and the catalog permission to the quotes screen', function () {
    $project = Project::factory()->create();
    Package::factory()->has(PackageItem::factory()->count(2), 'items')->create(['name' => 'Hochzeit Basis']);
    Package::factory()->inactive()->create(['name' => 'Alt']);
    CatalogItem::create(['title' => 'Zweitfotograf', 'unit_price' => 49000]);

    $props = $this->actingAs(quoteOwner(), 'staff')
        ->get("/auftraege/{$project->public_id}/angebote")
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['packages'])->pluck('name')->all())->toBe(['Hochzeit Basis'])
        ->and($props['packages'][0]['items'])->toHaveCount(2)
        ->and($props['catalogItems'][0]['title'])->toBe('Zweitfotograf')
        ->and($props['canManageCatalog'])->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test --filter="library and the catalog permission"`
Expected: FAIL — `Undefined array key "packages"`

- [ ] **Step 3: Write minimal implementation**

In `ProjectController::quotes()` einsetzen, mit den Importen für `PackageData`, `CatalogItem`, `CatalogItemData`, `Package`:

```php
'packages' => PackageData::collect(Package::active()->with('items')->orderBy('name')->get()),
'catalogItems' => CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get()),
'canManageCatalog' => $request->user('staff')?->can('create', Package::class) ?? false,
```

`quotes()` bekommt dafür `Request $request` in die Signatur.

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add apps/studio/app/Crm/Http/Controllers/ProjectController.php apps/studio/tests/Feature/Quotes/QuoteTest.php
git commit -m "feat(quotes): Pakete und Bibliothek als Prop"
```

---

### Task 5: Filtern und Gruppieren

**Files:**
- Create: `apps/studio/resources/js/admin/lib/insert.ts`
- Test: `apps/studio/resources/js/admin/lib/insert.test.ts`

**Interfaces:**
- Produces:
  - `type InsertOption = { kind: 'package' | 'item' | 'new'; id: string; label: string; meta: string }`
  - `insertOptions(term: string, packages: App.Catalog.Data.PackageData[], items: App.Catalog.Data.CatalogItemData[]): InsertOption[]`
  - `packageRows(pkg: App.Catalog.Data.PackageData): QuoteRow[]` und `itemRow(item: App.Catalog.Data.CatalogItemData): QuoteRow` — `QuoteRow` stammt aus `QuoteTable.vue` (S1, Task 8)

- [ ] **Step 1: Write the failing test**

`apps/studio/resources/js/admin/lib/insert.test.ts`:

```ts
import { describe, expect, it } from 'vitest'
import { insertOptions, itemRow, packageRows } from './insert'

const pkg = (name: string, items: { title: string; unitPrice: number | null; quantity?: number }[]) =>
  ({
    id: `p-${name}`,
    name,
    description: null,
    isActive: true,
    itemCount: items.length,
    items: items.map((item, index) => ({
      id: `pi-${index}`,
      title: item.title,
      description: null,
      quantity: item.quantity ?? 1,
      unitPrice: item.unitPrice,
    })),
  }) as unknown as App.Catalog.Data.PackageData

const item = (title: string, unitPrice: number | null) =>
  ({ id: `c-${title}`, title, description: null, unitPrice }) as App.Catalog.Data.CatalogItemData

describe('insertOptions', () => {
  it('ranks a match at the start of the title above one in the middle', () => {
    const result = insertOptions('foto', [], [item('Zweitfotograf', 49000), item('Fotobuch', 38000)])

    expect(result.filter((o) => o.kind === 'item').map((o) => o.label)).toEqual(['Fotobuch', 'Zweitfotograf'])
  })

  it('ignores case and matches packages as well as items', () => {
    const result = insertOptions('ALB', [pkg('Album-Paket', [{ title: 'Buch', unitPrice: 38000 }])], [item('Album gross', 21000)])

    expect(result.map((o) => o.kind)).toContain('package')
    expect(result.map((o) => o.kind)).toContain('item')
  })

  it('always offers the typed term as a new line, even when something matches', () => {
    const result = insertOptions('Fotobuch', [], [item('Fotobuch', 38000)])

    expect(result.filter((o) => o.kind === 'new')).toHaveLength(1)
  })

  it('offers nothing at all for an empty term', () => {
    expect(insertOptions('', [pkg('Album-Paket', [])], [item('Fotobuch', 38000)])).toEqual([])
  })
})

describe('packageRows', () => {
  it('copies every line of a package, keeping order and the included marker', () => {
    const rows = packageRows(pkg('Basis', [
      { title: 'Reportage', unitPrice: 189000, quantity: 1 },
      { title: 'Anfahrt', unitPrice: null },
    ]))

    expect(rows.map((row) => row.title)).toEqual(['Reportage', 'Anfahrt'])
    expect(rows[0].unitPrice).toBe('1890.00')
    expect(rows[1].unitPrice).toBe('')
  })
})

describe('itemRow', () => {
  it('turns a library line into an empty-discount row of quantity one', () => {
    expect(itemRow(item('Zweitfotograf', 49000))).toEqual({
      title: 'Zweitfotograf',
      description: '',
      quantity: 1,
      unitPrice: '490.00',
      discount: '',
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- resources/js/admin/lib/insert.test.ts`
Expected: FAIL — `Failed to resolve import "./insert"`

- [ ] **Step 3: Write minimal implementation**

`apps/studio/resources/js/admin/lib/insert.ts`:

```ts
import type { QuoteRow } from '@/admin/components/QuoteTable.vue'
import { centsToEuros, formatEurosRounded } from '@/admin/lib/money'

export type InsertOption = {
  kind: 'package' | 'item' | 'new'
  id: string
  label: string
  meta: string
}

/** Treffer am Wortanfang vor Treffer in der Mitte, sonst kein Rang. */
function rank(title: string, term: string): number {
  const index = title.toLowerCase().indexOf(term)

  return index < 0 ? Number.POSITIVE_INFINITY : index
}

function packageTotal(pkg: App.Catalog.Data.PackageData): number {
  return pkg.items.reduce((sum, item) => sum + (item.unitPrice ?? 0) * item.quantity, 0)
}

export function insertOptions(
  term: string,
  packages: App.Catalog.Data.PackageData[],
  items: App.Catalog.Data.CatalogItemData[],
): InsertOption[] {
  const needle = term.trim().toLowerCase()

  if (needle === '') {
    return []
  }

  const matched = <T>(rows: T[], title: (row: T) => string) =>
    rows
      .map((row) => ({ row, at: rank(title(row), needle) }))
      .filter((entry) => entry.at !== Number.POSITIVE_INFINITY)
      .sort((a, b) => a.at - b.at)
      .map((entry) => entry.row)

  return [
    ...matched(packages, (pkg) => pkg.name).map((pkg): InsertOption => ({
      kind: 'package',
      id: pkg.id,
      label: pkg.name,
      meta: `${pkg.itemCount} · ${formatEurosRounded(packageTotal(pkg))}`,
    })),
    ...matched(items, (item) => item.title).map((item): InsertOption => ({
      kind: 'item',
      id: item.id,
      label: item.title,
      meta: item.unitPrice === null ? '' : formatEurosRounded(item.unitPrice),
    })),
    // Steht auch bei Treffern da: derselbe Titel kann eine andere Position meinen.
    { kind: 'new', id: 'new', label: term.trim(), meta: '' },
  ]
}

export function packageRows(pkg: App.Catalog.Data.PackageData): QuoteRow[] {
  return pkg.items.map((item) => ({
    title: item.title,
    description: item.description ?? '',
    quantity: item.quantity,
    unitPrice: centsToEuros(item.unitPrice),
    discount: '',
  }))
}

export function itemRow(item: App.Catalog.Data.CatalogItemData): QuoteRow {
  return {
    title: item.title,
    description: item.description ?? '',
    quantity: 1,
    unitPrice: centsToEuros(item.unitPrice),
    discount: '',
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- resources/js/admin/lib/insert.test.ts`
Expected: PASS, 6 Tests

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/lib/insert.ts apps/studio/resources/js/admin/lib/insert.test.ts
git commit -m "feat(quotes): Bausteine filtern und in Zeilen wandeln"
```

---

### Task 6: `ui/combobox`

Die in `components.json` konfigurierte Registry führt keine Combobox. `reka-ui` bringt die Primitiven mit; der Wrapper wird lokal gebaut, nach dem Muster von `ui/select`.

**Files:**
- Create: `apps/studio/resources/js/ui/combobox/Combobox.vue`
- Create: `apps/studio/resources/js/ui/combobox/ComboboxInput.vue`
- Create: `apps/studio/resources/js/ui/combobox/ComboboxContent.vue`
- Create: `apps/studio/resources/js/ui/combobox/ComboboxGroup.vue`
- Create: `apps/studio/resources/js/ui/combobox/ComboboxLabel.vue`
- Create: `apps/studio/resources/js/ui/combobox/ComboboxItem.vue`
- Create: `apps/studio/resources/js/ui/combobox/index.ts`

**Interfaces:**
- Produces: Exporte `Combobox`, `ComboboxInput`, `ComboboxContent`, `ComboboxGroup`, `ComboboxLabel`, `ComboboxItem` aus `@/ui/combobox`

Kein Vitest — nichts lässt sich mounten. Die Absicherung ist Task 7, wo die Combobox in Gebrauch geht.

- [ ] **Step 1: Wurzel und Eingabe anlegen**

`Combobox.vue`:

```vue
<script setup lang="ts">
import type { ComboboxRootEmits, ComboboxRootProps } from 'reka-ui'
import { ComboboxRoot, useForwardPropsEmits } from 'reka-ui'

const props = defineProps<ComboboxRootProps>()
const emits = defineEmits<ComboboxRootEmits>()

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <ComboboxRoot data-slot="combobox" v-bind="forwarded" class="relative">
    <slot />
  </ComboboxRoot>
</template>
```

`ComboboxInput.vue`:

```vue
<script setup lang="ts">
import type { ComboboxInputProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxInput, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxInputProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <ComboboxInput
    data-slot="combobox-input"
    v-bind="forwarded"
    :class="cn(
      'w-full rounded-[var(--r-sm)] border border-line bg-background px-[11px] py-[7px]',
      'text-[13px] leading-[1.35] outline-none',
      'focus-visible:border-ring',
      props.class,
    )"
  />
</template>
```

- [ ] **Step 2: Liste, Gruppe, Beschriftung, Eintrag anlegen**

`ComboboxContent.vue`:

```vue
<script setup lang="ts">
import type { ComboboxContentProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxContent, ComboboxPortal, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxContentProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <ComboboxPortal>
    <ComboboxContent
      data-slot="combobox-content"
      v-bind="forwarded"
      position="popper"
      :class="cn(
        'z-50 max-h-72 min-w-[var(--reka-combobox-trigger-width)] overflow-y-auto',
        'rounded-[var(--r-sm)] border border-line bg-background p-1 shadow-lg',
        props.class,
      )"
    >
      <slot />
    </ComboboxContent>
  </ComboboxPortal>
</template>
```

`ComboboxGroup.vue` reicht `ComboboxGroup` aus `reka-ui` durch. `ComboboxLabel.vue` rendert `ComboboxLabel` mit den Klassen, die `SelectLabel.vue` schon verwendet — von dort abschreiben, damit die Gruppenbeschriftung in beiden Listen gleich aussieht.

`ComboboxItem.vue`:

```vue
<script setup lang="ts">
import type { ComboboxItemEmits, ComboboxItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxItemProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<ComboboxItemEmits>()

const delegatedProps = reactiveOmit(props, 'class')
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ComboboxItem
    data-slot="combobox-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex cursor-pointer select-none items-center gap-2 rounded-[var(--r-sm)] px-[11px] py-[7px]',
      'text-[13px] leading-[1.35] outline-none',
      'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
      props.class,
    )"
  >
    <slot />
  </ComboboxItem>
</template>
```

`index.ts` exportiert alle sechs nach dem Muster von `ui/select/index.ts`.

- [ ] **Step 3: Typprüfung**

Run: `npm run lint`
Expected: grün

- [ ] **Step 4: Commit**

```bash
git add apps/studio/resources/js/ui/combobox
git commit -m "feat(ui): Combobox auf reka-ui"
```

---

### Task 7: `QuoteInsertRow.vue` und der Einbau

**Files:**
- Create: `apps/studio/resources/js/admin/components/QuoteInsertRow.vue`
- Modify: `apps/studio/resources/js/admin/components/QuoteTable.vue`
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue`

**Interfaces:**
- Consumes: `insertOptions()`, `packageRows()`, `itemRow()` aus Task 5; `ui/combobox` aus Task 6; Props aus Task 4
- Produces: Komponente mit Props `packages`, `catalogItems` und Emit `insert(rows: QuoteRow[])`

- [ ] **Step 1: Komponente anlegen**

`apps/studio/resources/js/admin/components/QuoteInsertRow.vue`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'
import type { QuoteRow } from '@/admin/components/QuoteTable.vue'
import { insertOptions, itemRow, packageRows } from '@/admin/lib/insert'
import { Combobox, ComboboxContent, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxLabel } from '@/ui/combobox'

const props = defineProps<{
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
}>()

const emit = defineEmits<{ insert: [rows: QuoteRow[]] }>()

const term = ref('')

const options = computed(() => insertOptions(term.value, props.packages, props.catalogItems))

const groups = computed(() => [
  { key: 'groupPackages', options: options.value.filter((option) => option.kind === 'package') },
  { key: 'groupItems', options: options.value.filter((option) => option.kind === 'item') },
  { key: 'groupNew', options: options.value.filter((option) => option.kind === 'new') },
].filter((group) => group.options.length))

function choose(id: string, kind: string) {
  if (kind === 'package') {
    const pkg = props.packages.find((entry) => entry.id === id)
    emit('insert', pkg ? packageRows(pkg) : [])
  } else if (kind === 'item') {
    const item = props.catalogItems.find((entry) => entry.id === id)
    emit('insert', item ? [itemRow(item)] : [])
  } else {
    emit('insert', [{ title: term.value.trim(), description: '', quantity: 1, unitPrice: '', discount: '' }])
  }

  term.value = ''
}
</script>

<template>
  <!-- ignore-filter: gefiltert wird in insertOptions(), damit Rangfolge und
       die Gruppe "Neu" prüfbar bleiben statt in der Bibliothek zu stecken. -->
  <Combobox ignore-filter open-on-focus>
    <ComboboxInput v-model="term" :placeholder="$t('quotes.insert')" />
    <ComboboxContent v-if="options.length">
      <ComboboxGroup v-for="group in groups" :key="group.key">
        <ComboboxLabel>{{ $t(`quotes.${group.key}`) }}</ComboboxLabel>
        <ComboboxItem
          v-for="option in group.options"
          :key="`${option.kind}-${option.id}`"
          :value="`${option.kind}:${option.id}`"
          @select="choose(option.id, option.kind)"
        >
          <span class="min-w-0 flex-1 truncate">
            {{ option.kind === 'new' ? $t('quotes.insertNew', { term: option.label }) : option.label }}
          </span>
          <span v-if="option.meta" class="text-[12px] tabular-nums text-ink-2">{{ option.meta }}</span>
        </ComboboxItem>
      </ComboboxGroup>
    </ComboboxContent>
  </Combobox>
</template>
```

- [ ] **Step 2: In `QuoteTable.vue` einhängen**

Der Knopf „Position hinzufügen" weicht der Suchzeile — die freie Zeile entsteht jetzt über die Gruppe „Neu". `QuoteTable.vue` nimmt die beiden Props entgegen, reicht sie durch und hängt an das Emit:

```ts
function insert(newRows: QuoteRow[]) {
  rows.value = [...rows.value, ...newRows]
  // Fokus in die erste eingefügte Zeile, nicht in die letzte.
  nextTick(() => titleInputs.value[rows.value.length - newRows.length]?.focus())
}
```

`titleInputs` als Template-Ref-Array über die Titelzellen führen.

- [ ] **Step 3: In `Projects/Quotes.vue` die Props durchreichen**

`packages` und `catalogItems` aus `defineProps` an `QuoteTable` weitergeben. Nach `insert` läuft der Autosave aus S1 unverändert — es ist dieselbe Änderung am Array wie eine getippte Zeile.

- [ ] **Step 4: Prüfen**

Run: `npm run lint && npm run test`
Run: `docker compose exec -e APP_SURFACE=admin api php artisan test tests/Feature/Quotes/QuoteTest.php`
Expected: grün

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/admin/components/QuoteInsertRow.vue apps/studio/resources/js/admin/components/QuoteTable.vue apps/studio/resources/js/admin/pages/Projects/Quotes.vue
git commit -m "feat(quotes): Suchzeile fuer Pakete und Positionen"
```

---

### Task 8: Die Merken-Spalte

**Files:**
- Modify: `apps/studio/resources/js/admin/components/QuoteTable.vue`
- Modify: `apps/studio/resources/js/admin/pages/Projects/Quotes.vue`

**Interfaces:**
- Consumes: `canManageCatalog` aus Task 4, `items.*.remember` aus Task 2

- [ ] **Step 1: `QuoteRow` erweitern**

```ts
export type QuoteRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  remember: boolean
}
```

`packageRows()` und `itemRow()` in `lib/insert.ts` um `remember: false` beziehungsweise `remember: true` ergänzen — eine Zeile, die aus der Bibliothek kommt, ist per Definition gemerkt. Den Test in Task 5 entsprechend nachziehen.

- [ ] **Step 2: Spalte rendern**

Rasterspalte `32px` vor der Entfernen-Spalte, nur wenn `canManageCatalog`:

```vue
<Checkbox
  v-if="canManageCatalog"
  :model-value="row.remember"
  :aria-label="$t('quotes.remember')"
  @update:model-value="(value) => (row.remember = value === true)"
/>
```

- [ ] **Step 3: Ins Payload aufnehmen**

Im `transform()` in `Projects/Quotes.vue` je Zeile ergänzen — aber nur, wenn die Fähigkeit besteht, damit der Schlüssel sonst gar nicht erst mitgeht und der Wächter aus Task 3 nicht ohne Anlass greift:

```ts
...(props.canManageCatalog ? { remember: row.remember } : {}),
```

- [ ] **Step 4: Bestehenden Zustand ableiten**

Beim Aufbau der Zeilen aus `props.quotes` ist `remember` wahr, wenn ein `catalogItems`-Eintrag denselben Titel trägt:

```ts
remember: props.catalogItems.some((item) => item.title === quoteItem.title),
```

- [ ] **Step 5: Prüfen und committen**

Run: `npm run lint && npm run test`

```bash
git add apps/studio/resources/js/admin/components/QuoteTable.vue apps/studio/resources/js/admin/pages/Projects/Quotes.vue apps/studio/resources/js/admin/lib/insert.ts apps/studio/resources/js/admin/lib/insert.test.ts
git commit -m "feat(quotes): Position merken in der Tabelle"
```

---

### Task 9: Beschriftungen und e2e-Aufnahmen

**Files:**
- Modify: `apps/studio/resources/js/locales/de.json`

- [ ] **Step 1: Schlüssel ergänzen**

Unter `quotes`:

```json
"insert": "Position oder Paket",
"insertNew": "„{term}\" anlegen",
"remember": "Merken",
"groupPackages": "Pakete",
"groupItems": "Positionen",
"groupNew": "Neu"
```

- [ ] **Step 2: Toten Schlüssel prüfen**

`quotes.addItem` beschriftete den Knopf, den die Suchzeile ersetzt:

```bash
cd apps/studio && grep -rn "quotes\.addItem" resources/js || echo "tot"
```

Kein Treffer heißt: entfernen.

- [ ] **Step 3: Kein Slop**

Die drei Nachbarn jedes neuen Schlüssels lesen. `insertNew` ist der einzige mit Platzhalter — er benennt eine Handlung, er erklärt sie nicht.

- [ ] **Step 4: e2e**

Run: `npm run test:e2e`
Betroffen ist die Angebotsseite. Frisch säen, dann Baselines erneuern.

- [ ] **Step 5: Commit**

```bash
git add apps/studio/resources/js/locales/de.json
git commit -m "chore(i18n): Beschriftungen fuer die Suchzeile"
```

---

## Parallelisierung

| Welle | Tasks | Grund |
|---|---|---|
| A | 1, 5, 6 | keine gemeinsame Datei, keine Abhängigkeit |
| B | 2, 4 | brauchen Task 1; beide fassen `QuoteTest.php` an → **nacheinander**, oder gebündelt an einen Agenten |
| C | 3 | braucht Task 2, schreibt `QuoteController` und `QuoteTest.php` |
| D | 7, dann 8 | beide schreiben `QuoteTable.vue` und `Quotes.vue` → ein Agent für beide |
| E | 9 | braucht alles |

Kritischer Pfad: `1 → 2 → 3 → 7+8 → 9`. Drei Tasks laufen in Welle A gleichzeitig; danach zwingt `QuoteTest.php` und `QuoteTable.vue` alles in eine Reihe.

## Self-Review

**Spec-Abdeckung.** Migration und Modell → Task 1. Merken → Task 2 und Task 8. Berechtigung → Task 3. Props → Task 4. Filtern und Gruppieren → Task 5. Combobox-Wrapper → Task 6. Suchzeile und Paket einfügen → Task 7. Beschriftungen → Task 9.

**Zwei Stellen, die beim Umsetzen auffallen.**

`QuoteRow` bekommt in Task 8 ein Feld, das Task 5 schon in seinen Tests hätte haben können. Das ist bewusst so geschnitten: Task 5 ist reine Umwandlung und soll ohne Berechtigungsbegriff prüfbar sein. Wer beide hintereinander ausführt, zieht den Test in Task 8 nach — der Plan sagt es dort ausdrücklich.

Task 3 kann als Skip enden, wenn heute jede Rolle `ManagePackages` hält. Das ist kein Ausweichen: der Wächter existiert für API-Token aus P6/I-01, und der Skip hält fest, warum er trotzdem im Code steht.

**Was dieser Plan nicht enthält.** Kein Pflegescreen für die Bibliothek, keine Preishistorie, keine Unschärfesuche als Abhängigkeit, kein Fremdschlüssel zwischen `quote_items` und `catalog_items`.
